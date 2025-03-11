<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المستخدم
$user = new User($conn);

// متغيرات لتخزين رسائل الخطأ والقيم
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email'];
$phone = '';
$password = $confirm_password = '';
$full_name_err = $email_err = $phone_err = $password_err = $confirm_password_err = '';
$success_message = $error_message = '';

// الحصول على معلومات المستخدم الحالية
$user->readOne($_SESSION['user_id']);
$phone = $user->phone;

// معالجة تحديث المعلومات الشخصية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    
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
        if ($user->isEmailExists($email, $_SESSION['user_id'])) {
            $email_err = 'هذا البريد الإلكتروني مستخدم بالفعل';
        }
    }
    
    // التحقق من رقم الهاتف
    $phone = trim($_POST['phone']);
    
    // التحقق من الأخطاء قبل التحديث
    if (empty($full_name_err) && empty($email_err) && empty($phone_err)) {
        // تعيين خصائص المستخدم
        $user->id = $_SESSION['user_id'];
        $user->full_name = $full_name;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $_SESSION['role']; // لا تغيير في الدور
        
        // محاولة تحديث المعلومات
        if ($user->update()) {
            // تحديث متغيرات الجلسة
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            $success_message = 'تم تحديث المعلومات الشخصية بنجاح';
        } else {
            $error_message = 'حدث خطأ أثناء تحديث المعلومات';
        }
    }
}

// معالجة تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    
    // التحقق من كلمة المرور الجديدة
    if (empty(trim($_POST['password']))) {
        $password_err = 'الرجاء إدخال كلمة المرور الجديدة';
    } elseif (strlen(trim($_POST['password'])) < 6) {
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
    
    // التحقق من الأخطاء قبل التحديث
    if (empty($password_err) && empty($confirm_password_err)) {
        // محاولة تغيير كلمة المرور
        if ($user->changePassword($_SESSION['user_id'], $password)) {
            $success_message = 'تم تغيير كلمة المرور بنجاح';
        } else {
            $error_message = 'حدث خطأ أثناء تغيير كلمة المرور';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'الملف الشخصي';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">الملف الشخصي</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="<?php echo URL_ROOT; ?>/index.php">الرئيسية</a></li>
        <li class="breadcrumb-item active">الملف الشخصي</li>
    </ol>
    
    <?php if (!empty($success_message)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- معلومات المستخدم -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    معلومات المستخدم
                </div>
                <div class="card-body text-center">
                    <img class="img-account-profile rounded-circle mb-2" src="<?php echo ASSETS_URL; ?>/images/user.png" alt="صورة المستخدم" width="150">
                    <div class="small font-italic text-muted mb-4">يمكنك تحديث معلوماتك الشخصية وكلمة المرور</div>
                    <h4 class="mb-1"><?php echo $_SESSION['full_name']; ?></h4>
                    <div class="mb-1">
                        <span class="badge bg-primary">
                            <?php 
                                $role_name = '';
                                switch ($_SESSION['role']) {
                                    case 'admin':
                                        $role_name = 'مدير النظام';
                                        break;
                                    case 'manager':
                                        $role_name = 'مدير مبيعات';
                                        break;
                                    case 'salesperson':
                                        $role_name = 'مندوب مبيعات';
                                        break;
                                }
                                echo $role_name;
                            ?>
                        </span>
                    </div>
                    <p class="mb-0"><i class="fas fa-envelope me-1"></i> <?php echo $_SESSION['email']; ?></p>
                    <p class="mb-0"><i class="fas fa-phone me-1"></i> <?php echo $phone ?: 'غير محدد'; ?></p>
                    <p class="mb-0"><i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['username']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- تحديث المعلومات الشخصية -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab" aria-controls="info" aria-selected="true">
                                <i class="fas fa-user-edit me-1"></i> تحديث المعلومات
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab" aria-controls="password" aria-selected="false">
                                <i class="fas fa-key me-1"></i> تغيير كلمة المرور
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabContent">
                        <!-- تحديث المعلومات -->
                        <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="full_name">الاسم الكامل</label>
                                    <input class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" type="text" value="<?php echo $full_name; ?>" required>
                                    <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="email">البريد الإلكتروني</label>
                                    <input class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" type="email" value="<?php echo $email; ?>" required>
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="phone">رقم الهاتف</label>
                                    <input class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" id="phone" name="phone" type="text" value="<?php echo $phone; ?>">
                                    <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                                </div>
                                <button class="btn btn-primary" name="update_info" type="submit">
                                    <i class="fas fa-save me-1"></i> حفظ التغييرات
                                </button>
                            </form>
                        </div>
                        
                        <!-- تغيير كلمة المرور -->
                        <div class="tab-pane fade" id="password" role="tabpanel" aria-labelledby="password-tab">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="password">كلمة المرور الجديدة</label>
                                    <input class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" type="password" required>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                    <div class="form-text">يجب أن تتكون كلمة المرور من 6 أحرف على الأقل</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور</label>
                                    <input class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" type="password" required>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                </div>
                                <button class="btn btn-primary" name="change_password" type="submit">
                                    <i class="fas fa-key me-1"></i> تغيير كلمة المرور
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>