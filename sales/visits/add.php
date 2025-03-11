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
require_once '../../classes/Client.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$visit = new Visit($conn);
$client = new Client($conn);

// متغيرات لتخزين قيم النموذج
$company_name = $client_name = $client_phone = $visit_time = $purpose = $notes = '';

// متغيرات لتخزين رسائل الخطأ
$company_name_err = $client_name_err = $client_phone_err = $visit_time_err = $purpose_err = '';

// الحصول على العملاء للاقتراحات
$clients_list = $client->readAll();
$clients_json = json_encode($clients_list);

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
        
        // التحقق من عدم وجود تعارض في المواعيد
        if (empty($visit_time_err) && $visit->checkTimeConflict($_SESSION['user_id'], $visit_time)) {
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
    
    // التحقق من عدم وجود أخطاء قبل إنشاء الزيارة
    if (empty($company_name_err) && empty($client_name_err) && empty($client_phone_err) && 
        empty($visit_time_err) && empty($purpose_err)) {
        
        // تعيين خصائص الزيارة
        $visit->salesperson_id = $_SESSION['user_id'];
        $visit->company_name = $company_name;
        $visit->client_name = $client_name;
        $visit->client_phone = $client_phone;
        $visit->visit_time = $visit_time;
        $visit->purpose = $purpose;
        $visit->visit_status = 'planned';
        $visit->notes = $notes;
        
        // محاولة إنشاء الزيارة
        if ($visit->create()) {
            // إعادة التوجيه إلى صفحة قائمة الزيارات مع رسالة نجاح
            $_SESSION['success_message'] = 'تم إضافة الزيارة بنجاح';
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء إضافة الزيارة';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة زيارة جديدة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة زيارة جديدة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">الزيارات الخارجية</a></li>
        <li class="breadcrumb-item active">إضافة زيارة جديدة</li>
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
            <i class="fas fa-calendar-plus me-1"></i>
            تفاصيل الزيارة الجديدة
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
                        <input type="datetime-local" class="form-control <?php echo (!empty($visit_time_err)) ? 'is-invalid' : ''; ?>" id="visit_time" name="visit_time" value="<?php echo $visit_time; ?>">
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
                
                <!-- اقتراحات من قائمة العملاء -->
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="fas fa-lightbulb me-1"></i>
                        اختيار من العملاء السابقين
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">يمكنك اختيار أحد العملاء السابقين لتعبئة البيانات تلقائيًا</p>
                        <div class="mb-3">
                            <label for="client_select" class="form-label">اختر العميل</label>
                            <select class="form-select" id="client_select">
                                <option value="">-- اختر العميل --</option>
                                <?php foreach ($clients_list as $client_item) : ?>
                                    <option value="<?php echo $client_item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($client_item['name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($client_item['phone']); ?>"
                                            data-email="<?php echo htmlspecialchars($client_item['email']); ?>"
                                            data-address="<?php echo htmlspecialchars($client_item['address']); ?>">
                                        <?php echo htmlspecialchars($client_item['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- زر الحفظ والإلغاء -->
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ الزيارة
                    </button>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ملء بيانات العميل عند الاختيار من القائمة
    const clientSelect = document.getElementById('client_select');
    clientSelect.addEventListener('change', function() {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('client_name').value = selectedOption.getAttribute('data-name');
            document.getElementById('client_phone').value = selectedOption.getAttribute('data-phone');
            
            // يمكن استخدام معلومات أخرى إذا كانت مطلوبة
            const clientEmail = selectedOption.getAttribute('data-email');
            const clientAddress = selectedOption.getAttribute('data-address');
            
            // يمكنك أيضًا تخمين اسم الشركة من العنوان إذا كان فارغًا
            if (document.getElementById('company_name').value === '' && clientAddress) {
                const addressParts = clientAddress.split(',');
                if (addressParts.length > 0) {
                    document.getElementById('company_name').value = addressParts[0].trim();
                }
            }
        }
    });
    
    // تعيين وقت افتراضي للزيارة (بعد ساعة من الوقت الحالي)
    if (!document.getElementById('visit_time').value) {
        const now = new Date();
        now.setHours(now.getHours() + 1);
        now.setMinutes(0);
        
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hour = String(now.getHours()).padStart(2, '0');
        const minute = String(now.getMinutes()).padStart(2, '0');
        
        const defaultTime = `${year}-${month}-${day}T${hour}:${minute}`;
        document.getElementById('visit_time').value = defaultTime;
    }
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>