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

// متغيرات لتخزين قيم النموذج
$full_name = $username = $email = $phone = $role = $manager_id = '';
$password = $confirm_password = '';

// متغيرات لتخزين رسائل الخطأ
$full_name_err = $username_err = $email_err = $phone_err = $role_err = $manager_err = '';
$password_err = $confirm_password_err = '';

// متغير لتخزين رسالة النجاح
$success_message = '';

// الحصول على قائمة المديرين للقائمة المنسدلة
$managers = $user->readAll(['role' => 'manager']);

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من الاسم الكامل
    if (empty(trim($_POST['full_name']))) {
        $full_name_err = 'الرجاء إدخال الاسم الكامل';
    } else {
        $full_name = trim($_POST['full_name']);
    }
    
    // التحقق من اسم المستخدم
    if (empty(trim($_POST['username']))) {
        $username_err = 'الرجاء إدخال اسم المستخدم';
    } else {
        $username = trim($_POST['username']);
        
        // التحقق من وجود اسم المستخدم
        if ($user->isUsernameExists($username)) {
            $username_err = 'اسم المستخدم موجود بالفعل';
        }
    }
    
    // التحقق من البريد الإلكتروني
    if (empty(trim($_POST['email']))) {
        $email_err = 'الرجاء إدخال البريد الإلكتروني';
    } elseif (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $email_err = 'الرجاء إدخال بريد إلكتروني صحيح';
    } else {
        $email = trim($_POST['email']);
        
        // التحقق من وجود البريد الإلكتروني
        if ($user->isEmailExists($email)) {
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
    if ($role === 'salesperson') {
        if (empty($_POST['manager_id'])) {
            $manager_err = 'الرجاء اختيار مدير للمندوب';
        } else {
            $manager_id = (int)$_POST['manager_id'];
        }
    }
    
    // التحقق من كلمة المرور
    if (empty(trim($_POST['password']))) {
        $password_err = 'الرجاء إدخال كلمة المرور';
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
    
    // التحقق من عدم وجود أخطاء قبل إدخال المستخدم في قاعدة البيانات
    if (empty($full_name_err) && empty($username_err) && empty($email_err) && 
        empty($role_err) && empty($manager_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // تعيين خصائص المستخدم
        $user->full_name = $full_name;
        $user->username = $username;
        $user->password = $password;
        $user->email = $email;
        $user->phone = $phone;
        $user->role = $role;
        $user->manager_id = ($role === 'salesperson' && !empty($manager_id)) ? $manager_id : null;
        
        // محاولة إنشاء المستخدم
        if ($user->create()) {
            $success_message = 'تم إنشاء المستخدم بنجاح';
            
            // إعادة تعيين قيم النموذج
            $full_name = $username = $email = $phone = $role = $manager_id = '';
            $password = $confirm_password = '';
        } else {
            $error_message = 'حدث خطأ أثناء إنشاء المستخدم';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة مستخدم جديد';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة مستخدم جديد</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة المستخدمين</a></li>
        <li class="breadcrumb-item active">إضافة مستخدم جديد</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (!empty($success_message)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-plus me-1"></i>
            معلومات المستخدم الجديد
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
                        <div class="invalid-feedback"><?php echo $full_name_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo $username; ?>" required>
                        <div class="invalid-feedback"><?php echo $username_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $email; ?>" required>
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo $phone; ?>">
                        <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="role" class="form-label">الدور <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" id="role" name="role" required>
                            <option value="" <?php echo empty($role) ? 'selected' : ''; ?>>اختر الدور</option>
                            <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>مسؤول النظام</option>
                            <option value="manager" <?php echo ($role === 'manager') ? 'selected' : ''; ?>>مدير مبيعات</option>
                            <option value="salesperson" <?php echo ($role === 'salesperson') ? 'selected' : ''; ?>>مندوب مبيعات</option>
                        </select>
                        <div class="invalid-feedback"><?php echo $role_err; ?></div>
                    </div>
                    <div class="col-md-6" id="manager_container" style="<?php echo ($role !== 'salesperson') ? 'display: none;' : ''; ?>">
                        <label for="manager_id" class="form-label">المدير المسؤول <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo (!empty($manager_err)) ? 'is-invalid' : ''; ?>" id="manager_id" name="manager_id">
                            <option value="" <?php echo empty($manager_id) ? 'selected' : ''; ?>>اختر المدير</option>
                            <?php foreach ($managers as $manager) : ?>
                                <option value="<?php echo $manager['id']; ?>" <?php echo ($manager_id == $manager['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($manager['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $manager_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <div class="invalid-feedback"><?php echo $password_err; ?></div>
                        <div class="form-text">يجب أن تتكون كلمة المرور من 6 أحرف على الأقل</div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ المستخدم
                    </button>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript لإظهار/إخفاء حقل المدير بناءً على الدور المختار -->
<script>
    document.getElementById('role').addEventListener('change', function() {
        const managerContainer = document.getElementById('manager_container');
        if (this.value === 'salesperson') {
            managerContainer.style.display = 'block';
        } else {
            managerContainer.style.display = 'none';
        }
    });
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>