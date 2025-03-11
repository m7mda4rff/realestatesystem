<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول بالفعل
if (isset($_SESSION['user_id'])) {
    // توجيه المستخدم بناءً على الدور
    if ($_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
    } elseif ($_SESSION['role'] === 'manager') {
        header('Location: ../manager/index.php');
    } else {
        header('Location: ../sales/index.php');
    }
    exit;
}

// تضمين ملف ثوابت النظام
require_once '../config/constants.php';

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config/database.php';
require_once '../classes/User.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المستخدم
$user = new User($conn);

// متغيرات لتخزين رسائل الخطأ والقيم
$username = $password = '';
$username_err = $password_err = $login_err = '';

// معالجة البيانات عند تقديم النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من اسم المستخدم
    if (empty(trim($_POST['username']))) {
        $username_err = 'الرجاء إدخال اسم المستخدم';
    } else {
        $username = trim($_POST['username']);
    }
    
    // التحقق من كلمة المرور
    if (empty(trim($_POST['password']))) {
        $password_err = 'الرجاء إدخال كلمة المرور';
    } else {
        $password = trim($_POST['password']);
    }
    
    // التحقق من الأخطاء قبل المصادقة
    if (empty($username_err) && empty($password_err)) {
        // محاولة تسجيل الدخول
        $result = $user->login($username, $password);
        
        // التحقق من نجاح تسجيل الدخول
        if (!empty($result)) {
            // بدء جلسة جديدة
            session_start();
            
            // تخزين البيانات في متغيرات الجلسة
            $_SESSION['user_id'] = $result['id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['full_name'] = $result['full_name'];
            $_SESSION['email'] = $result['email'];
            $_SESSION['role'] = $result['role'];
            
            // توجيه المستخدم بناءً على الدور
            if ($result['role'] === 'admin') {
                header('Location: ../admin/index.php');
            } elseif ($result['role'] === 'manager') {
                header('Location: ../manager/index.php');
            } else {
                header('Location: ../sales/index.php');
            }
            exit;
        } else {
            // فشل تسجيل الدخول
            $login_err = 'اسم المستخدم أو كلمة المرور غير صحيحة';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo SYSTEM_NAME; ?></title>
    <!-- تضمين Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <!-- تضمين Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            margin-top: 100px;
        }
        .card-header {
            background-color: #343a40;
            color: white;
            text-align: center;
            padding: 20px;
        }
        .system-name {
            font-size: 24px;
            font-weight: bold;
        }
        .system-version {
            font-size: 14px;
        }
        .login-icon {
            font-size: 80px;
            color: #343a40;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card shadow-lg border-0">
                <div class="card-header">
                    <div class="system-name"><?php echo SYSTEM_NAME; ?></div>
                    <div class="system-version">الإصدار <?php echo SYSTEM_VERSION; ?></div>
                </div>
                <div class="card-body">
                    <div class="login-icon">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    
                    <h4 class="text-center mb-4">تسجيل الدخول إلى النظام</h4>
                    
                    <?php if (!empty($login_err)) : ?>
                        <div class="alert alert-danger"><?php echo $login_err; ?></div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">اسم المستخدم</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" placeholder="أدخل اسم المستخدم" value="<?php echo $username; ?>">
                                <div class="invalid-feedback"><?php echo $username_err; ?></div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">كلمة المرور</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="أدخل كلمة المرور">
                                <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">تذكرني</label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="reset-password.php">نسيت كلمة المرور؟</a></div>
                </div>
            </div>
            <p class="text-center mt-4">
                <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SYSTEM_NAME; ?>. جميع الحقوق محفوظة.</small>
            </p>
        </div>
    </div>
    
    <!-- تضمين Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>