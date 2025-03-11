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
require_once '../../classes/Target.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$user = new User($conn);

// التحقق من معرف الهدف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$target_id = (int)$_GET['id'];

// الحصول على معلومات الهدف
if (!$target->readOne($target_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على الهدف المطلوب';
    header('Location: index.php');
    exit;
}

// متغيرات لتخزين قيم النموذج
$salesperson_id = $target->salesperson_id;
$target_amount = $target->target_amount;
$start_date = $target->start_date;
$end_date = $target->end_date;
$notes = $target->notes;

// متغيرات لتخزين رسائل الخطأ
$target_amount_err = $start_date_err = $end_date_err = '';

// الحصول على قائمة مندوبي المبيعات
$salespeople = $user->readAll(['role' => 'salesperson']);

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من مبلغ الهدف
    if (empty($_POST['target_amount'])) {
        $target_amount_err = 'يرجى إدخال مبلغ الهدف';
    } else {
        $target_amount = (float)str_replace(',', '', $_POST['target_amount']);
        if ($target_amount <= 0) {
            $target_amount_err = 'يجب أن يكون مبلغ الهدف أكبر من الصفر';
        }
    }
    
    // التحقق من تاريخ البداية
    if (empty($_POST['start_date'])) {
        $start_date_err = 'يرجى إدخال تاريخ البداية';
    } else {
        $start_date = $_POST['start_date'];
    }
    
    // التحقق من تاريخ النهاية
    if (empty($_POST['end_date'])) {
        $end_date_err = 'يرجى إدخال تاريخ النهاية';
    } else {
        $end_date = $_POST['end_date'];
        
        // التحقق من أن تاريخ النهاية بعد تاريخ البداية
        if (strtotime($end_date) <= strtotime($start_date)) {
            $end_date_err = 'يجب أن يكون تاريخ النهاية بعد تاريخ البداية';
        }
    }
    
    // التحقق من عدم تداخل الفترات لنفس المندوب
    if (empty($start_date_err) && empty($end_date_err)) {
        if ($target->checkDateOverlap($salesperson_id, $start_date, $end_date, $target_id)) {
            $start_date_err = 'يوجد هدف آخر لنفس المندوب في هذه الفترة الزمنية';
        }
    }
    
    // التحقق من عدم وجود أخطاء قبل حفظ الهدف
    if (empty($target_amount_err) && empty($start_date_err) && empty($end_date_err)) {
        // تعيين قيم الهدف
        $target->id = $target_id;
        $target->target_amount = $target_amount;
        $target->start_date = $start_date;
        $target->end_date = $end_date;
        $target->notes = $_POST['notes'] ?? '';
        
        // محاولة تحديث الهدف
        if ($target->update()) {
            // إعادة التوجيه إلى صفحة قائمة الأهداف مع رسالة نجاح
            $_SESSION['success_message'] = 'تم تحديث الهدف بنجاح';
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء تحديث الهدف';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'تعديل الهدف';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تعديل الهدف</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة الأهداف</a></li>
        <li class="breadcrumb-item active">تعديل الهدف</li>
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
            تعديل تفاصيل الهدف
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $target_id; ?>">
                <!-- مندوب المبيعات - للعرض فقط -->
                <div class="mb-3">
                    <label for="salesperson_name" class="form-label">مندوب المبيعات</label>
                    <?php 
                        $salesperson_name = '';
                        foreach ($salespeople as $person) {
                            if ($person['id'] == $salesperson_id) {
                                $salesperson_name = $person['full_name'];
                                break;
                            }
                        }
                    ?>
                    <input type="text" class="form-control" id="salesperson_name" value="<?php echo $salesperson_name; ?>" readonly>
                    <div class="form-text">لا يمكن تغيير مندوب المبيعات للهدف الحالي</div>
                </div>
                
                <!-- مبلغ الهدف -->
                <div class="mb-3">
                    <label for="target_amount" class="form-label">مبلغ الهدف <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control <?php echo (!empty($target_amount_err)) ? 'is-invalid' : ''; ?>" id="target_amount" name="target_amount" value="<?php echo number_format($target_amount, 2); ?>" placeholder="أدخل مبلغ الهدف">
                        <span class="input-group-text">ج.م</span>
                        <div class="invalid-feedback"><?php echo $target_amount_err; ?></div>
                    </div>
                </div>
                
                <!-- المبلغ المحقق (للعرض فقط) -->
                <div class="mb-3">
                    <label for="achieved_amount" class="form-label">المبلغ المحقق</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="achieved_amount" value="<?php echo number_format($target->achieved_amount, 2); ?>" readonly>
                        <span class="input-group-text">ج.م</span>
                    </div>
                    <div class="form-text">المبلغ المحقق حتى الآن</div>
                </div>
                
                <!-- نسبة الإنجاز (للعرض فقط) -->
                <div class="mb-3">
                    <label class="form-label">نسبة الإنجاز</label>
                    <?php 
                        $achievement_percentage = calculateAchievement($target->achieved_amount, $target_amount);
                        $progress_color = getColorByPercentage($achievement_percentage);
                    ?>
                    <div class="progress">
                        <div class="progress-bar bg-<?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo min($achievement_percentage, 100); ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $achievement_percentage; ?>%
                        </div>
                    </div>
                </div>
                
                <!-- الفترة الزمنية -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">تاريخ البداية <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo (!empty($start_date_err)) ? 'is-invalid' : ''; ?>" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        <div class="invalid-feedback"><?php echo $start_date_err; ?></div>
                    </div>
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">تاريخ النهاية <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?php echo (!empty($end_date_err)) ? 'is-invalid' : ''; ?>" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        <div class="invalid-feedback"><?php echo $end_date_err; ?></div>
                    </div>
                </div>
                
                <!-- قوالب الفترات الزمنية للاختيار السريع -->
                <div class="mb-3">
                    <label class="form-label">اختيار سريع للفترة الزمنية</label>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('current_month')">الشهر الحالي</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('next_month')">الشهر القادم</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('current_quarter')">الربع الحالي</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('next_quarter')">الربع القادم</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setPeriod('current_year')">السنة الحالية</button>
                    </div>
                </div>
                
                <!-- ملاحظات -->
                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo $notes; ?></textarea>
                </div>
                
                <!-- أزرار الإجراءات -->
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

<script>
// وظيفة لتعيين الفترة الزمنية بناءً على الاختيار السريع
function setPeriod(period) {
    const today = new Date();
    let startDate = new Date();
    let endDate = new Date();
    
    switch (period) {
        case 'current_month':
            // بداية الشهر الحالي
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            // نهاية الشهر الحالي
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'next_month':
            // بداية الشهر القادم
            startDate = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            // نهاية الشهر القادم
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
            const nextQuarterYear = today.getFullYear() + (nextQuarter > 3 ? 1 : 0);
            const adjustedNextQuarter = nextQuarter > 3 ? 0 : nextQuarter;
            startDate = new Date(nextQuarterYear, adjustedNextQuarter * 3, 1);
            endDate = new Date(nextQuarterYear, (adjustedNextQuarter + 1) * 3, 0);
            break;
        case 'current_year':
            // السنة الحالية
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = new Date(today.getFullYear(), 11, 31);
            break;
    }
    
    // تحويل التواريخ إلى الصيغة المطلوبة (YYYY-MM-DD)
    document.getElementById('start_date').value = formatDate(startDate);
    document.getElementById('end_date').value = formatDate(endDate);
}

// وظيفة لتنسيق التاريخ بالصيغة المطلوبة
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// تنسيق حقل مبلغ الهدف بإضافة الفواصل
document.addEventListener('DOMContentLoaded', function() {
    const targetAmountInput = document.getElementById('target_amount');
    
    targetAmountInput.addEventListener('blur', function() {
        if (this.value) {
            const num = parseFloat(this.value.replace(/,/g, ''));
            if (!isNaN(num)) {
                this.value = num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
    });
    
    // تنظيف الأرقام عند إرسال النموذج
    document.querySelector('form').addEventListener('submit', function() {
        targetAmountInput.value = targetAmountInput.value.replace(/,/g, '');
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>