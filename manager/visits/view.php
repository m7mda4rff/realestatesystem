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
require_once '../../classes/Visit.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$visit = new Visit($conn);
$user = new User($conn);

// الحصول على معرف المستخدم من الجلسة
$manager_id = $_SESSION['user_id'];

// التحقق من وجود معرف الزيارة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف الزيارة غير صحيح';
    header('Location: index.php');
    exit;
}

$visit_id = (int)$_GET['id'];

// قراءة بيانات الزيارة الحالية
if (!$visit->readOne($visit_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على الزيارة المطلوبة';
    header('Location: index.php');
    exit;
}

// الحصول على قائمة مندوبي المبيعات التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);

// التحقق من أن المندوب يتبع للمدير الحالي
$belongs_to_manager = false;
foreach ($salespeople as $sp) {
    if ($sp['id'] === $visit->salesperson_id) {
        $belongs_to_manager = true;
        break;
    }
}

if (!$belongs_to_manager) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية عرض هذه الزيارة';
    header('Location: index.php');
    exit;
}

// معالجة تحديث حالة الزيارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['status'];
    $outcome = isset($_POST['outcome']) ? $_POST['outcome'] : '';
    
    if ($visit->updateStatus($visit_id, $new_status, $outcome)) {
        $_SESSION['success_message'] = 'تم تحديث حالة الزيارة بنجاح';
        // إعادة تحميل الصفحة لعرض التحديثات
        header('Location: view.php?id=' . $visit_id);
        exit;
    } else {
        $error_message = 'حدث خطأ أثناء تحديث حالة الزيارة';
    }
}

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل الزيارة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل الزيارة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">متابعة الزيارات</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل الزيارة</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($_SESSION['success_message'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- بطاقة تفاصيل الزيارة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    تفاصيل الزيارة
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات الزيارة</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم الزيارة</th>
                                    <td>#<?php echo $visit->id; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">المندوب</th>
                                    <td><?php echo htmlspecialchars($visit->salesperson_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ ووقت الزيارة</th>
                                    <td><?php echo date('Y-m-d H:i', strtotime($visit->visit_time)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة الزيارة</th>
                                    <td>
                                        <?php if ($visit->visit_status === 'planned') : ?>
                                            <span class="badge visit-planned">مخططة</span>
                                        <?php elseif ($visit->visit_status === 'completed') : ?>
                                            <span class="badge visit-completed">مكتملة</span>
                                        <?php else : ?>
                                            <span class="badge visit-cancelled">ملغية</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo date('Y-m-d', strtotime($visit->created_at)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">آخر تحديث</th>
                                    <td><?php echo date('Y-m-d', strtotime($visit->updated_at)); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات العميل</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">اسم الشركة</th>
                                    <td><?php echo htmlspecialchars($visit->company_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">اسم العميل</th>
                                    <td><?php echo htmlspecialchars($visit->client_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">رقم الهاتف</th>
                                    <td>
                                        <a href="tel:<?php echo htmlspecialchars($visit->client_phone); ?>">
                                            <?php echo htmlspecialchars($visit->client_phone); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h5 class="card-title">غرض الزيارة</h5>
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <?php echo nl2br(htmlspecialchars($visit->purpose)); ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($visit->outcome)) : ?>
                                <h5 class="card-title">نتيجة الزيارة</h5>
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($visit->outcome)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($visit->notes)) : ?>
                                <h5 class="card-title">ملاحظات إضافية</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($visit->notes)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة الإجراءات -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cogs me-1"></i>
                    الإجراءات المتاحة
                </div>
                <div class="card-body">
                    <!-- حالة الزيارة الحالية -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading">حالة الزيارة الحالية</h6>
                        <?php if ($visit->visit_status === 'planned') : ?>
                            <span class="badge visit-planned fs-6">مخططة</span>
                            <p class="mb-0 small mt-2">هذه الزيارة مخططة ولم تتم بعد.</p>
                        <?php elseif ($visit->visit_status === 'completed') : ?>
                            <span class="badge visit-completed fs-6">مكتملة</span>
                            <p class="mb-0 small mt-2">تم إكمال هذه الزيارة بنجاح.</p>
                        <?php else : ?>
                            <span class="badge visit-cancelled fs-6">ملغية</span>
                            <p class="mb-0 small mt-2">تم إلغاء هذه الزيارة.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- نموذج تحديث حالة الزيارة -->
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">تحديث حالة الزيارة</label>
                            <select class="form-select" id="status" name="status">
                                <option value="planned" <?php echo ($visit->visit_status === 'planned') ? 'selected' : ''; ?>>مخططة</option>
                                <option value="completed" <?php echo ($visit->visit_status === 'completed') ? 'selected' : ''; ?>>مكتملة</option>
                                <option value="cancelled" <?php echo ($visit->visit_status === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="outcome-container" style="<?php echo ($visit->visit_status !== 'completed') ? 'display: none;' : ''; ?>">
                            <label for="outcome" class="form-label">نتيجة الزيارة</label>
                            <textarea class="form-control" id="outcome" name="outcome" rows="5" placeholder="أدخل نتيجة الزيارة وأهم النقاط التي تمت مناقشتها..."><?php echo $visit->outcome; ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> تحديث الحالة
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> العودة إلى القائمة
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- بطاقة معلومات المندوب -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    معلومات المندوب
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <h5 class="card-title text-center mb-3"><?php echo htmlspecialchars($visit->salesperson_name); ?></h5>
                    
                    <div class="list-group">
                        <?php if (!empty($visit->salesperson_email)) : ?>
                            <a href="mailto:<?php echo $visit->salesperson_email; ?>" class="list-group-item list-group-item-action">
                                <i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($visit->salesperson_email); ?>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($visit->salesperson_phone)) : ?>
                            <a href="tel:<?php echo $visit->salesperson_phone; ?>" class="list-group-item list-group-item-action">
                                <i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($visit->salesperson_phone); ?>
                            </a>
                        <?php endif; ?>
                        
                        <!-- روابط إضافية -->
                        <a href="../salespeople/index.php?id=<?php echo $visit->salesperson_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-line me-2"></i> عرض أداء المندوب
                        </a>
                        <a href="../targets/index.php?salesperson_id=<?php echo $visit->salesperson_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-bullseye me-2"></i> أهداف المندوب
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // إظهار/إخفاء حقل النتيجة بناءً على الحالة المحددة
    const statusSelect = document.getElementById('status');
    const outcomeContainer = document.getElementById('outcome-container');
    
    statusSelect.addEventListener('change', function() {
        if (this.value === 'completed') {
            outcomeContainer.style.display = 'block';
        } else {
            outcomeContainer.style.display = 'none';
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>