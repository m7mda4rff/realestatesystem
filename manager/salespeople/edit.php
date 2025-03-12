<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المستخدم
$user = new User($conn);

// التحقق من وجود معرف المستخدم في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$salesperson_id = (int)$_GET['id'];

// محاولة قراءة معلومات المندوب
if (!$user->readOne($salesperson_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المندوب';
    header('Location: index.php');
    exit;
}

// التحقق من أن المندوب يتبع للمدير الحالي
if ($user->manager_id != $_SESSION['user_id']) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية تعديل هذا المندوب';
    header('Location: index.php');
    exit;
}

// متغيرات لتخزين رسائل الخطأ
$full_name_err = $email_err = $phone_err = $password_err = $confirm_password_err = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من الاسم الكامل
    if (empty(trim($_POST['full_name']))) {
        $full_name_err = 'الرجاء إدخال الاسم الكامل';
    } else {
        $full_name = trim($_POST['full_name']);
    }
    
    // التحقق من البريد الإلكتروني
    if (empty(trim($_POST['email']))) {
        $email_err = 'الرجاء إدخال البريد الإلكتروني';
    } elseif (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $email_err = 'الرجاء إدخال بريد إلكتروني صحيح';
    } else {
        $email = trim($_POST['email']);
        
        // التحقق من أن البريد الإلكتروني غير مستخدم مسبقاً (باستثناء المستخدم الحالي)
        if ($user->isEmailExists($email, $salesperson_id)) {
            $email_err = 'البريد الإلكتروني مستخدم بالفعل، الرجاء اختيار بريد آخر';
        }
    }
    
    // الهاتف (اختياري)
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // التحقق من كلمة المرور (اختيارية للتحديث)
    $change_password = false;
    $password = '';
    
    if (!empty(trim($_POST['password']))) {
        $change_password = true;
        
        if (strlen(trim($_POST['password'])) < 6) {
            $password_err = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل';
        } else {
            $password = trim($_POST['password']);
        }
        
        // التحقق من تأكيد كلمة المرور
        if (empty(trim($_POST['confirm_password']))) {
            $confirm_password_err = 'الرجاء تأكيد كلمة المرور';
        } else {
            $confirm_password = trim($_POST['confirm_password']);
            if (empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = 'كلمات المرور غير متطابقة';
            }
        }
    }
    
    // التحقق من عدم وجود أخطاء قبل تحديث المستخدم
    if (empty($full_name_err) && empty($email_err) && 
        empty($password_err) && empty($confirm_password_err)) {
        
        // تعيين خصائص المستخدم
        $user->id = $salesperson_id;
        $user->full_name = $full_name;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = 'salesperson'; // تأكيد على الدور
        $user->manager_id = $_SESSION['user_id']; // تأكيد على المدير
        
        // محاولة تحديث المستخدم
        if ($user->update()) {
            // إذا تم تحديد كلمة مرور جديدة، قم بتحديثها
            if ($change_password && $user->changePassword($salesperson_id, $password)) {
                $_SESSION['success_message'] = 'تم تحديث بيانات المندوب وكلمة المرور بنجاح';
            } else {
                $_SESSION['success_message'] = 'تم تحديث بيانات المندوب بنجاح';
            }
            
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء تحديث بيانات المندوب';
        }
    }
}

// استخدام قيم المستخدم الحالية لملء النموذج
$full_name = $user->full_name;
$email = $user->email;
$phone = $user->phone;

// تعيين عنوان الصفحة
$page_title = 'تعديل بيانات المندوب';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تعديل بيانات المندوب</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة المندوبين</a></li>
        <li class="breadcrumb-item active">تعديل بيانات المندوب: <?php echo htmlspecialchars($user->full_name); ?></li>
    </ol>
    
    <!-- رسائل الخطأ -->
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-edit me-1"></i>
            تعديل بيانات المندوب
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $salesperson_id; ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <div class="form-text">اتركه فارغاً إذا لم يكن متوفراً</div>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">اسم المستخدم</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user->username); ?>" readonly>
                        <div class="form-text">لا يمكن تغيير اسم المستخدم</div>
                    </div>
                </div>
                
                <div class="card mb-3 bg-light">
                    <div class="card-body">
                        <h5 class="card-title">تغيير كلمة المرور</h5>
                        <p class="card-text">اترك الحقول فارغة إذا كنت لا ترغب في تغيير كلمة المرور</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="password" class="form-label">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                <div class="form-text">يجب أن تتكون كلمة المرور من 6 أحرف على الأقل</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                                <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ التغييرات
                    </button>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>