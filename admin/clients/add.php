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
require_once '../../classes/Client.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة العميل
$client = new Client($conn);

// متغيرات لتخزين قيم النموذج
$name = $phone = $email = $address = $notes = '';

// متغيرات لتخزين رسائل الخطأ
$name_err = $phone_err = $email_err = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من اسم العميل
    if (empty(trim($_POST['name']))) {
        $name_err = 'الرجاء إدخال اسم العميل';
    } else {
        $name = trim($_POST['name']);
    }
    
    // التحقق من رقم الهاتف (اختياري ولكن يجب أن يكون صحيحًا إذا تم إدخاله)
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // التحقق من البريد الإلكتروني (اختياري ولكن يجب أن يكون صحيحًا إذا تم إدخاله)
    if (!empty(trim($_POST['email']))) {
        if (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
            $email_err = 'الرجاء إدخال بريد إلكتروني صحيح';
        } else {
            $email = trim($_POST['email']);
        }
    }
    
    // الحصول على بقية البيانات
    $address = !empty($_POST['address']) ? trim($_POST['address']) : '';
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // التحقق من عدم وجود أخطاء قبل إدخال البيانات في قاعدة البيانات
    if (empty($name_err) && empty($phone_err) && empty($email_err)) {
        
        // تعيين خصائص العميل
        $client->name = $name;
        $client->phone = $phone;
        $client->email = $email;
        $client->address = $address;
        $client->notes = $notes;
        $client->created_by = $_SESSION['user_id'];
        
        // محاولة إنشاء العميل
        if ($client->create()) {
            // تعيين رسالة النجاح وإعادة التوجيه
            $_SESSION['success_message'] = 'تم إضافة العميل بنجاح';
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء إضافة العميل. الرجاء المحاولة مرة أخرى.';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة عميل جديد';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة عميل جديد</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة العملاء</a></li>
        <li class="breadcrumb-item active">إضافة عميل جديد</li>
    </ol>
    
    <!-- عرض رسالة الخطأ إذا وجدت -->
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-plus me-1"></i>
            تفاصيل العميل الجديد
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo $name; ?>" required>
                        <div class="invalid-feedback"><?php echo $name_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo $phone; ?>">
                        <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $email; ?>">
                        <div class="invalid-feedback"><?php echo $email_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label">العنوان</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo $address; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes; ?></textarea>
                </div>
                
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ العميل
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