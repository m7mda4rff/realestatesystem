<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومندوب مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Visit.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة الزيارات
$visit = new Visit($conn);

// التحقق من وجود معرف الزيارة في العنوان
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$visit_id = (int)$_GET['id'];

// قراءة بيانات الزيارة الحالية
$visit->readOne($visit_id);

// التحقق من أن الزيارة تنتمي للمندوب الحالي
if ($visit->salesperson_id !== $_SESSION['user_id']) {
    $_SESSION['error_message'] = 'غير مصرح لك بتعديل هذه الزيارة';
    header('Location: index.php');
    exit;
}

// التحقق من أن الزيارة مخططة (لا يمكن تعديل الزيارات المكتملة أو الملغية)
if ($visit->visit_status !== 'planned') {
    $_SESSION['error_message'] = 'لا يمكن تعديل الزيارات المكتملة أو الملغية';
    header('Location: index.php');
    exit;
}

// متغيرات لتخزين قيم النموذج
$company_name = $visit->company_name;
$client_name = $visit->client_name;
$client_phone = $visit->client_phone;
$visit_time = $visit->visit_time;
$purpose = $visit->purpose;
$notes = $visit->notes;

// متغيرات لتخزين رسائل الخطأ
$company_name_err = $client_name_err = $client_phone_err = $visit_time_err = $purpose_err = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من اسم الشركة
    if (empty(trim($_POST['company_name']))) {
        $company_name_err = 'الرجاء إدخال اسم الشركة';
    } else {
        $company_name = trim($_POST['company_name']);
    }
    
    // التحقق من اسم العميل
    if (empty(trim($_POST['client_name']))) {
        $client_name_err = 'الرجاء إدخال اسم العميل';
    } else {
        $client_name = trim($_POST['client_name']);
    }
    
    // التحقق من هاتف العميل
    if (empty(trim($_POST['client_phone']))) {
        $client_phone_err = 'الرجاء إدخال هاتف العميل';
    } else {
        $client_phone = trim($_POST['client_phone']);
    }
    
    // التحقق من وقت الزيارة
    if (empty($_POST['visit_time'])) {
        $visit_time_err = 'الرجاء تحديد وقت الزيارة';
    } else {
        $visit_time = $_POST['visit_time'];
        
        // التحقق من أن وقت الزيارة ليس في الماضي
        if (strtotime($visit_time) < time()) {
            $visit_time_err = 'لا يمكن تحديد وقت زيارة في الماضي';
        }
        
        // التحقق من عدم وجود تعارض في المواعيد (مع استثناء الزيارة الحالية)
        if (empty($visit_time_err) && $visit->checkTimeConflict($_SESSION['user_id'], $visit_time, 60, $visit_id)) {
            $visit_time_err = 'يوجد تعارض مع زيارة أخرى في نفس الوقت';
        }
    }
    
    // التحقق من غرض الزيارة
    if (empty(trim($_POST['purpose']))) {
        $purpose_err = 'الرجاء إدخال غرض الزيارة';
    } else {
        $purpose = trim($_POST['purpose']);
    }
    
    // الملاحظات (اختيارية)
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // التحقق من عدم وجود أخطاء قبل تحديث الزيارة
    if (empty($company_name_err) && empty($client_name_err) && empty($client_phone_err) && 
        empty($visit_time_err) && empty($purpose_err)) {
        
        // تعيين خصائص الزيارة
        $visit->id = $visit_id;
        $visit->company_name = $company_name;
        $visit->client_name = $client_name;
        $visit->client_phone = $client_phone;
        $visit->visit_time = $visit_time;
        $visit->purpose = $purpose;
        $visit->notes = $notes;
        
        // محاولة تحديث الزيارة
        if ($visit->update()) {
            // إعادة التوجيه إلى صفحة قائمة الزيارات مع رسالة نجاح
            $_SESSION['success_message'] = 'تم تحديث الزيارة بنجاح';
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء تحديث الزيارة';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'تعديل الزيارة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تعديل الزيارة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">الزيارات الخارجية</a></li>
        <li class="breadcrumb-item active">تعديل الزيارة</li>
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
            <i class="fas fa-calendar-alt me-1"></i>
            تعديل تفاصيل الزيارة
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $visit_id; ?>">
                <!-- الشركة والعميل -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label">اسم الشركة <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($company_name_err)) ? 'is-invalid' : ''; ?>" id="company_name" name="company_name" placeholder="أدخل اسم الشركة" value="<?php echo $company_name; ?>">
                        <div class="invalid-feedback"><?php echo $company_name_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="client_name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($client_name_err)) ? 'is-invalid' : ''; ?>" id="client_name" name="client_name" placeholder="أدخل اسم العميل" value="<?php echo $client_name; ?>">
                        <div class="invalid-feedback"><?php echo $client_name_err; ?></div>
                    </div>
                </div>
                
                <!-- هاتف العميل ووقت الزيارة -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="client_phone" class="form-label">هاتف العميل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo (!empty($client_phone_err)) ? 'is-invalid' : ''; ?>" id="client_phone" name="client_phone" placeholder="أدخل رقم هاتف العميل" value="<?php echo $client_phone; ?>">
                        <div class="invalid-feedback"><?php echo $client_phone_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="visit_time" class="form-label">وقت الزيارة <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control <?php echo (!empty($visit_time_err)) ? 'is-invalid' : ''; ?>" id="visit_time" name="visit_time" value="<?php echo date('Y-m-d\TH:i', strtotime($visit_time)); ?>">
                        <div class="invalid-feedback"><?php echo $visit_time_err; ?></div>
                    </div>
                </div>
                
                <!-- غرض الزيارة -->
                <div class="mb-3">
                    <label for="purpose" class="form-label">غرض الزيارة <span class="text-danger">*</span></label>
                    <textarea class="form-control <?php echo (!empty($purpose_err)) ? 'is-invalid' : ''; ?>" id="purpose" name="purpose" rows="3" placeholder="أدخل غرض الزيارة وتفاصيلها"><?php echo $purpose; ?></textarea>
                    <div class="invalid-feedback"><?php echo $purpose_err; ?></div>
                </div>
                
                <!-- ملاحظات إضافية -->
                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات إضافية</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="أدخل أي ملاحظات إضافية"><?php echo $notes; ?></textarea>
                </div>
                
                <!-- زر الحفظ والإلغاء -->
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