<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Target.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$user = new User($conn);

// الحصول على معرف المستخدم من الجلسة
$manager_id = $_SESSION['user_id'];

// الحصول على قائمة مندوبي المبيعات التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);

// متغيرات لتخزين قيم النموذج
$salesperson_id = isset($_GET['salesperson_id']) ? (int)$_GET['salesperson_id'] : '';
$target_amount = '';
$start_date = date('Y-m-01'); // أول يوم في الشهر الحالي
$end_date = date('Y-m-t'); // آخر يوم في الشهر الحالي
$notes = '';

// متغيرات لتخزين رسائل الخطأ
$salesperson_id_err = $target_amount_err = $start_date_err = $end_date_err = '';

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من وجود المندوب
    if (empty($_POST['salesperson_id'])) {
        $salesperson_id_err = 'الرجاء اختيار مندوب المبيعات';
    } else {
        $salesperson_id = (int)$_POST['salesperson_id'];
        
        // التحقق من أن المندوب يتبع للمدير الحالي
        $belongs_to_manager = false;
        foreach ($salespeople as $sp) {
            if ($sp['id'] === $salesperson_id) {
                $belongs_to_manager = true;
                break;
            }
        }
        
        if (!$belongs_to_manager) {
            $salesperson_id_err = 'المندوب المحدد غير صحيح';
        }
    }
    
    // التحقق من المبلغ المستهدف
    if (empty($_POST['target_amount'])) {
        $target_amount_err = 'الرجاء إدخال المبلغ المستهدف';
    } elseif (!is_numeric($_POST['target_amount']) || $_POST['target_amount'] <= 0) {
        $target_amount_err = 'الرجاء إدخال قيمة رقمية موجبة';
    } else {
        $target_amount = (float)$_POST['target_amount'];
    }
    
    // التحقق من تاريخ البداية
    if (empty($_POST['start_date'])) {
        $start_date_err = 'الرجاء تحديد تاريخ البداية';
    } else {
        $start_date = $_POST['start_date'];
    }
    
    // التحقق من تاريخ النهاية
    if (empty($_POST['end_date'])) {
        $end_date_err = 'الرجاء تحديد تاريخ النهاية';
    } else {
        $end_date = $_POST['end_date'];
        
        // التحقق من أن تاريخ النهاية بعد تاريخ البداية
        if (strtotime($end_date) < strtotime($start_date)) {
            $end_date_err = 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية';
        }
    }
    
    // التحقق من عدم وجود تداخل في الفترات
    if (empty($salesperson_id_err) && empty($start_date_err) && empty($end_date_err)) {
        if ($target->checkDateOverlap($salesperson_id, $start_date, $end_date)) {
            $start_date_err = 'يوجد تداخل مع هدف آخر لنفس المندوب في هذه الفترة';
        }
    }
    
    // الملاحظات (اختيارية)
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // التحقق من عدم وجود أخطاء قبل الحفظ
    if (empty($salesperson_id_err) && empty($target_amount_err) && empty($start_date_err) && empty($end_date_err)) {
        // تعيين خصائص الهدف
        $target->salesperson_id = $salesperson_id;
        $target->target_amount = $target_amount;
        $target->achieved_amount = 0; // البداية بصفر للهدف الجديد
        $target->start_date = $start_date;
        $target->end_date = $end_date;
        $target->notes = $notes;
        $target->created_by = $manager_id;
        
        // محاولة إنشاء الهدف
        if ($target->create()) {
            // إعادة التوجيه إلى صفحة قائمة الأهداف
            $_SESSION['success_message'] = 'تم إضافة الهدف بنجاح';
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء إضافة الهدف';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة هدف جديد';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة هدف جديد</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة الأهداف</a></li>
        <li class="breadcrumb-item active">إضافة هدف جديد</li>
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
            <i class="fas fa-bullseye me-1"></i>
            معلومات الهدف الجديد
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <!-- مندوب المبيعات -->
                <div class="mb-3">
                    <label for="salesperson_id" class="form-label">مندوب المبيعات <span class="text-danger">*</span></label>
                    <select class="form-select select2 <?php echo (!empty($salesperson_id_err)) ? 'is-invalid' : ''; ?>" id="salesperson_id" name="salesperson_id">
                        <option value="">اختر مندوب المبيعات</option>
                        <?php foreach ($salespeople as $sp) : ?>
                            <option value="<?php echo $sp['id']; ?>" <?php echo ($salesperson_id == $sp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sp['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?php echo $salesperson_id_err; ?></div>
                </div>
                
                <!-- المبلغ المستهدف -->
                <div class="mb-3">
                    <label for="target_amount" class="form-label">المبلغ المستهدف <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" class="form-control <?php echo (!empty($target_amount_err)) ? 'is-invalid' : ''; ?>" id="target_amount" name="target_amount" placeholder="أدخل المبلغ المستهدف" value="<?php echo $target_amount; ?>">
                        <span class="input-group-text">ج.م</span>
                        <div class="invalid-feedback"><?php echo $target_amount_err; ?></div>
                    </div>
                </div>
                
                <!-- فترة الهدف -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="start_date" class="form-label">تاريخ البداية <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo (!empty($start_date_err)) ? 'is-invalid' : ''; ?>" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        <div class="invalid-feedback"><?php echo $start_date_err; ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="end_date" class="form-label">تاريخ النهاية <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo (!empty($end_date_err)) ? 'is-invalid' : ''; ?>" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        <div class="invalid-feedback"><?php echo $end_date_err; ?></div>
                    </div>
                </div>
                
                <!-- فترة سريعة -->
                <div class="mb-3">
                    <label for="quick_period" class="form-label">تحديد فترة سريعة</label>
                    <select class="form-select" id="quick_period">
                        <option value="">اختر فترة محددة مسبقًا</option>
                        <option value="current_month">الشهر الحالي</option>
                        <option value="next_month">الشهر القادم</option>
                        <option value="current_quarter">الربع الحالي</option>
                        <option value="next_quarter">الربع القادم</option>
                        <option value="current_year">السنة الحالية</option>
                    </select>
                </div>
                
                <!-- ملاحظات -->
                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="أدخل أي ملاحظات إضافية..."><?php echo $notes; ?></textarea>
                </div>
                
                <!-- زر الحفظ والإلغاء -->
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ الهدف
                    </button>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript لتعبئة الفترات السريعة -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const quickPeriodSelect = document.getElementById('quick_period');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    quickPeriodSelect.addEventListener('change', function() {
        const selectedPeriod = this.value;
        const today = new Date();
        let startDate = new Date();
        let endDate = new Date();
        
        switch (selectedPeriod) {
            case 'current_month':
                // الشهر الحالي
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
                
            case 'next_month':
                // الشهر القادم
                startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 2, 0);
                break;
                
            case 'current_quarter':
                // الربع الحالي
                const currentQuarter = Math.floor(today.getMonth() / 3);
                startDate = new Date(today.getFullYear(), currentQuarter * 3, 1);
                endDate = new Date(today.getFullYear(), (currentQuarter + 1) * 3, 0);
                break;
                
            case 'next_quarter':
                // الربع القادم
                const nextQuarter = Math.floor(today.getMonth() / 3) + 1;
                startDate = new Date(today.getFullYear(), nextQuarter * 3, 1);
                endDate = new Date(today.getFullYear(), (nextQuarter + 1) * 3, 0);
                break;
                
            case 'current_year':
                // السنة الحالية
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
                
            default:
                return; // لا تغير التواريخ إذا لم يتم تحديد خيار
        }
        
        // تنسيق التواريخ وتعيينها في الحقول
        startDateInput.value = formatDate(startDate);
        endDateInput.value = formatDate(endDate);
    });
    
    // دالة لتنسيق التاريخ بصيغة YYYY-MM-DD
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>