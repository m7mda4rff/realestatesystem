<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    $_SESSION['error_message'] = 'معرف المستخدم غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المستخدم
$user_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل المستخدم
if (!$user->readOne($user_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المستخدم المطلوب';
    header('Location: index.php');
    exit;
}

// متغيرات لتخزين رسائل الخطأ
$full_name_err = $email_err = $phone_err = $role_err = $manager_err = '';
$password_err = $confirm_password_err = '';

// الحصول على قائمة المديرين للقائمة المنسدلة
$managers = [];
$managers_query = "SELECT id, full_name FROM users WHERE role = 'manager'";
$managers_result = $conn->query($managers_query);
while ($row = $managers_result->fetch_assoc()) {
    $managers[] = $row;
}

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
        
        // التحقق من وجود البريد الإلكتروني
        if ($user->isEmailExists($email, $user_id)) {
            $email_err = 'البريد الإلكتروني موجود بالفعل';
        }
    }
    
    // التحقق من رقم الهاتف (اختياري)
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // التحقق من الدور
    if (empty(trim($_POST['role']))) {
        $role_err = 'الرجاء اختيار دور المستخدم';
    } else {
        $role = trim($_POST['role']);
        
        // التحقق من صحة الدور
        $valid_roles = ['admin', 'manager', 'salesperson'];
        if (!in_array($role, $valid_roles)) {
            $role_err = 'دور غير صالح';
        }
    }
    
    // التحقق من المدير إذا كان الدور مندوب مبيعات
    $manager_id = null;
    if ($role === 'salesperson') {
        if (empty($_POST['manager_id'])) {
            $manager_err = 'الرجاء اختيار مدير للمندوب';
        } else {
            $manager_id = (int)$_POST['manager_id'];
        }
    }
    
    // التحقق من كلمة المرور (اختياري عند التعديل)
    $password = '';
    if (!empty(trim($_POST['password']))) {
        if (strlen(trim($_POST['password'])) < 6) {
            $password_err = 'يجب أن تتكون كلمة المرور من 6 أحرف على الأقل';
        } else {
            $password = trim($_POST['password']);
            
            // التحقق من تأكيد كلمة المرور
            if (empty(trim($_POST['confirm_password']))) {
                $confirm_password_err = 'الرجاء تأكيد كلمة المرور';
            } else {
                $confirm_password = trim($_POST['confirm_password']);
                if ($password != $confirm_password) {
                    $confirm_password_err = 'كلمات المرور غير متطابقة';
                }
            }
        }
    }
    
    // التحقق من عدم وجود أخطاء قبل تحديث المستخدم
    if (empty($full_name_err) && empty($email_err) && empty($role_err) && empty($manager_err) && empty($password_err) && empty($confirm_password_err)) {
        // تعيين خصائص المستخدم
        $user->id = $user_id;
        $user->full_name = $full_name;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $role;
        $user->manager_id = $manager_id;
        
        // محاولة تحديث المستخدم
        if ($user->update()) {
            // إذا تم توفير كلمة مرور جديدة، قم بتحديثها
            if (!empty($password)) {
                if ($user->changePassword($user_id, $password)) {
                    $_SESSION['success_message'] = 'تم تحديث بيانات المستخدم وكلمة المرور بنجاح';
                } else {
                    $_SESSION['error_message'] = 'تم تحديث بيانات المستخدم ولكن حدث خطأ أثناء تحديث كلمة المرور';
                }
            } else {
                $_SESSION['success_message'] = 'تم تحديث بيانات المستخدم بنجاح';
            }
            
            // إعادة التوجيه إلى صفحة عرض المستخدم
            header('Location: view.php?id=' . $user_id);
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء تحديث بيانات المستخدم';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'تعديل بيانات المستخدم';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تعديل بيانات المستخدم</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة المستخدمين</a></li>
        <li class="breadcrumb-item active">تعديل بيانات المستخدم</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-edit me-1"></i>
            تعديل بيانات المستخدم
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $user_id); ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user->full_name); ?>" required>
                        <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($user->email); ?>" required>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user->phone); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="role" class="form-label">الدور <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" id="role" name="role" required>
                            <option value="admin" <?php echo ($user->role === 'admin') ? 'selected' : ''; ?>>مسؤول النظام</option>
                            <option value="manager" <?php echo ($user->role === 'manager') ? 'selected' : ''; ?>>مدير مبيعات</option>
                            <option value="salesperson" <?php echo ($user->role === 'salesperson') ? 'selected' : ''; ?>>مندوب مبيعات</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $role_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6" id="manager_container" style="<?php echo ($user->role !== 'salesperson') ? 'display: none;' : ''; ?>">
                        <label for="manager_id" class="form-label">المدير المسؤول <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo (!empty($manager_err)) ? 'is-invalid' : ''; ?>" id="manager_id" name="manager_id">
                            <option value="">اختر المدير</option>
                            <?php foreach ($managers as $manager) : ?>
                                <option value="<?php echo $manager['id']; ?>" <?php echo ($user->manager_id == $manager['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($manager['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $manager_err; ?></div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h5>تغيير كلمة المرور (اختياري)</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password">
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <div class="form-text">اترك هذا الحقل فارغًا إذا كنت لا ترغب في تغيير كلمة المرور</div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                </div>
                
                <div class="mt-4 d-flex justify-content-between">
                    <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> إلغاء
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript لإظهار/إخفاء حقل المدير بناءً على الدور المختار -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        const managerContainer = document.getElementById('manager_container');
        
        roleSelect.addEventListener('change', function() {
            if (this.value === 'salesperson') {
                managerContainer.style.display = 'block';
            } else {
                managerContainer.style.display = 'none';
            }
        });
    });
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>