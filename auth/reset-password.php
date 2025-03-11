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

// متغيرات لتخزين رسائل الخطأ والقيم
$email = $username = '';
$email_err = $username_err = '';
$success_message = $error_message = '';

// معالجة البيانات عند تقديم النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من البريد الإلكتروني
    if (empty(trim($_POST['email']))) {
        $email_err = 'الرجاء إدخال البريد الإلكتروني';
    } elseif (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $email_err = 'الرجاء إدخال بريد إلكتروني صحيح';
    } else {
        $email = trim($_POST['email']);
    }
    
    // التحقق من اسم المستخدم
    if (empty(trim($_POST['username']))) {
        $username_err = 'الرجاء إدخال اسم المستخدم';
    } else {
        $username = trim($_POST['username']);
    }
    
    // التحقق من الأخطاء قبل المتابعة
    if (empty($email_err) && empty($username_err)) {
        // في تطبيق حقيقي، هنا سيتم إرسال رابط إعادة تعيين كلمة المرور إلى البريد الإلكتروني
        // لأغراض العرض، سنعرض رسالة نجاح فقط
        
        // إنشاء اتصال بقاعدة البيانات
        $db = new Database();
        $conn = $db->getConnection();
        
        // التحقق من وجود المستخدم
        $query = "SELECT id FROM users WHERE email = ? AND username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $success_message = 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني. يرجى التحقق من صندوق الوارد الخاص بك.';
            
            // تفريغ المتغيرات
            $email = $username = '';
        } else {
            $error_message = 'لم يتم العثور على حساب مطابق للبيانات المدخلة';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - <?php echo SYSTEM_NAME; ?></title>
    <!-- تضمين Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <!-- تضمين Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .reset-container {
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
        .reset-icon {
            font-size: 80px;
            color: #343a40;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-container">
            <div class="card shadow-lg border-0">
                <div class="card-header">
                    <div class="system-name"><?php echo SYSTEM_NAME; ?></div>
                </div>
                <div class="card-body">
                    <div class="reset-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    
                    <h4 class="text-center mb-4">إعادة تعيين كلمة المرور</h4>
                    
                    <?php if (!empty($success_message)) : ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-primary">العودة إلى تسجيل الدخول</a>
                        </div>
                    <?php else : ?>
                    
                        <?php if (!empty($error_message)) : ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">أدخل بريدك الإلكتروني واسم المستخدم وسيتم إرسال رابط لإعادة تعيين كلمة المرور</p>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" placeholder="أدخل بريدك الإلكتروني" value="<?php echo $email; ?>">
                                    <div class="invalid-feedback"><?php echo $email_err; ?></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" id="username" name="username" placeholder="أدخل اسم المستخدم" value="<?php echo $username; ?>">
                                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i> إرسال
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center py-3">
                    <div class="small"><a href="login.php">العودة إلى تسجيل الدخول</a></div>
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