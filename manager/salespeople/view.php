<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Sale.php';
require_once '../../classes/Target.php';
require_once '../../classes/Visit.php';
require_once '../../classes/Commission.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$user = new User($conn);
$sale = new Sale($conn);
$target = new Target($conn);
$visit = new Visit($conn);
$commission = new Commission($conn);

// الحصول على معرف المدير من الجلسة
$manager_id = $_SESSION['user_id'];

// التحقق من وجود معرف المندوب في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المندوب غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المندوب
$salesperson_id = (int)$_GET['id'];

// الحصول على معلومات المندوب
$user->readOne($salesperson_id);

// التحقق من أن المندوب يتبع للمدير الحالي
if ($user->manager_id !== $manager_id) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية الوصول لهذا المندوب';
    header('Location: index.php');
    exit;
}

// الحصول على الهدف الحالي للمندوب
$current_target = $target->getCurrentTarget($salesperson_id);
$target_amount = $current_target['target_amount'];
$achieved_amount = $current_target['achieved_amount'];
$target_percentage = calculateAchievement($achieved_amount, $target_amount);

// الحصول على إحصائيات المبيعات للمندوب
$current_month = date('Y-m');
$monthly_sales = $sale->getMonthlySales($salesperson_id, $current_month);
$sales_amount = $monthly_sales['amount'];
$sales_count = $monthly_sales['count'];
$commission_amount = $monthly_sales['commission'];

// الحصول على إحصائيات العمولات
$commission_stats = $commission->getCommissionStats($salesperson_id, 'month');
$pending_commission = $commission_stats['pending_amount'];
$paid_commission = $commission_stats['paid_amount'];

// الحصول على إحصائيات الزيارات
$visit_stats = $visit->getVisitStats($salesperson_id, 'month');
$total_visits = $visit_stats['total'];
$planned_visits = $visit_stats['planned'];
$completed_visits = $visit_stats['completed'];
$cancelled_visits = $visit_stats['cancelled'];

// الحصول على أحدث المبيعات للمندوب
$recent_sales = $sale->getRecentSales($salesperson_id, 5);

// الحصول على الزيارات القادمة للمندوب
$upcoming_visits = $visit->getUpcomingVisits($salesperson_id, 5);

// الحصول على الأهداف السابقة للمندوب
$previous_targets = $target->getTargetsBySalesperson($salesperson_id, 5);

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل المندوب - ' . $user->full_name;

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل المندوب</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة المندوبين</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل المندوب: <?php echo htmlspecialchars($user->full_name); ?></li>
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
    
    <div class="row">
        <!-- بطاقة معلومات المندوب -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    معلومات المندوب
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-6x text-primary"></i>
                        <h3 class="mt-3"><?php echo htmlspecialchars($user->full_name); ?></h3>
                        <div class="text-muted">مندوب مبيعات</div>
                    </div>
                    
                    <div class="list-group">
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-6 text-muted">اسم المستخدم:</div>
                                <div class="col-6"><?php echo htmlspecialchars($user->username); ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-6 text-muted">البريد الإلكتروني:</div>
                                <div class="col-6"><?php echo htmlspecialchars($user->email); ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-6 text-muted">رقم الهاتف:</div>
                                <div class="col-6"><?php echo htmlspecialchars($user->phone ?? 'غير متوفر'); ?></div>
                            </div>
                        </div>
                        <div class="list-group-item">
                            <div class="row">
                                <div class="col-6 text-muted">تاريخ الإنضمام:</div>
                                <div class="col-6"><?php echo date('Y-m-d', strtotime($user->created_at)); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="edit.php?id=<?php echo $salesperson_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> تعديل البيانات
                        </a>
                        <a href="../targets/add.php?salesperson_id=<?php echo $salesperson_id; ?>" class="btn btn-success">
                            <i class="fas fa-bullseye me-1"></i> إضافة هدف جديد
                        </a>
                        <a href="../sales/index.php?salesperson_id=<?php echo $salesperson_id; ?>" class="btn btn-info">
                            <i class="fas fa-shopping-cart me-1"></i> عرض المبيعات
                        </a>
                        <a href="../visits/index.php?salesperson_id=<?php echo $salesperson_id; ?>" class="btn btn-warning">
                            <i class="fas fa-calendar-check me-1"></i> عرض الزيارات
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <!-- بطاقة الملخص -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    ملخص الأداء الشهري
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card bg-primary text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">المبيعات الشهرية</div>
                                            <div class="display-6"><?php echo formatMoney($sales_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-shopping-cart fa-3x"></i></div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span class="small text-white">عدد المبيعات: <?php echo $sales_count; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card bg-success text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">العمولات المستحقة</div>
                                            <div class="display-6"><?php echo formatMoney($commission_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-coins fa-3x"></i></div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span class="small text-white">المدفوع: <?php echo formatMoney($paid_commission); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card bg-warning text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">تحقيق الهدف</div>
                                            <div class="display-6"><?php echo $target_percentage; ?>%</div>
                                        </div>
                                        <div><i class="fas fa-bullseye fa-3x"></i></div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span class="small text-white"><?php echo formatMoney($achieved_amount); ?> من <?php echo formatMoney($target_amount); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card bg-info text-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">الزيارات الشهرية</div>
                                            <div class="display-6"><?php echo $total_visits; ?></div>
                                        </div>
                                        <div><i class="fas fa-calendar-check fa-3x"></i></div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex align-items-center justify-content-between">
                                    <span class="small text-white">المكتملة: <?php echo $completed_visits; ?>, المخططة: <?php echo $planned_visits; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- شريط التقدم للهدف -->
                    <div class="mt-2">
                        <h5 class="card-title">تقدم تحقيق الهدف الشهري</h5>
                        <div class="progress" style="height: 25px;">
                            <?php $color = getColorByPercentage($target_percentage); ?>
                            <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo min($target_percentage, 100); ?>%;" aria-valuenow="<?php echo $target_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $target_percentage; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- آخر المبيعات والزيارات القادمة -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-shopping-cart me-1"></i>
                            آخر المبيعات
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_sales) > 0) : ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>العميل</th>
                                                <th>التاريخ</th>
                                                <th>المبلغ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sales as $sale_item) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات حديثة.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-calendar-alt me-1"></i>
                            الزيارات القادمة
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_visits) > 0) : ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>الشركة</th>
                                                <th>التاريخ</th>
                                                <th>الوقت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcoming_visits as $visit_item) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($visit_item['company_name']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($visit_item['visit_time'])); ?></td>
                                                    <td><?php echo date('h:i A', strtotime($visit_item['visit_time'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else : ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-1"></i> لا توجد زيارات قادمة مجدولة.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تقارير الأداء -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-line me-1"></i>
                    تطور المبيعات
                </div>
                <div class="card-body">
                    <canvas id="salesTrendChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزيع الزيارات
                </div>
                <div class="card-body">
                    <canvas id="visitsChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الأهداف السابقة -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-bullseye me-1"></i>
            الأهداف السابقة
        </div>
        <div class="card-body">
            <?php if (count($previous_targets) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الفترة</th>
                                <th>المبلغ المستهدف</th>
                                <th>المبلغ المحقق</th>
                                <th>نسبة التحقيق</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previous_targets as $index => $tgt) : ?>
                                <?php
                                    // تخطي الهدف الحالي إذا كان موجودًا في القائمة
                                    if (isset($current_target['id']) && $tgt['id'] == $current_target['id']) {
                                        continue;
                                    }
                                    
                                    $tgt_percentage = calculateAchievement($tgt['achieved_amount'], $tgt['target_amount']);
                                    $tgt_color = getColorByPercentage($tgt_percentage);
                                    $tgt_status = $tgt_percentage >= 100 ? 'مكتمل' : 'غير مكتمل';
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($tgt['start_date'])); ?> - 
                                        <?php echo date('Y-m-d', strtotime($tgt['end_date'])); ?>
                                    </td>
                                    <td><?php echo formatMoney($tgt['target_amount']); ?></td>
                                    <td><?php echo formatMoney($tgt['achieved_amount']); ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $tgt_color; ?>" role="progressbar" style="width: <?php echo min($tgt_percentage, 100); ?>%;" aria-valuenow="<?php echo $tgt_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $tgt_percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $tgt_percentage >= 100 ? 'success' : 'warning'; ?>">
                                            <?php echo $tgt_status; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد أهداف سابقة متاحة.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // بيانات تطور المبيعات
    var salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
    var salesTrendChart = new Chart(salesTrendCtx, {
        type: 'line',
        data: {
            labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
            datasets: [{
                label: 'المبيعات الشهرية (ج.م)',
                data: [
                    <?php 
                        // هذه البيانات افتراضية، يمكن استبدالها ببيانات حقيقية
                        echo rand(5000, 15000) . ', ';
                        echo rand(5000, 15000) . ', ';
                        echo rand(5000, 15000) . ', ';
                        echo rand(5000, 15000) . ', ';
                        echo rand(5000, 15000) . ', ';
                        echo $sales_amount;
                    ?>
                ],
                fill: false,
                borderColor: 'rgb(54, 162, 235)',
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // بيانات توزيع الزيارات
    var visitsCtx = document.getElementById('visitsChart').getContext('2d');
    var visitsChart = new Chart(visitsCtx, {
        type: 'pie',
        data: {
            labels: ['مخططة', 'مكتملة', 'ملغية'],
            datasets: [{
                data: [<?php echo $planned_visits; ?>, <?php echo $completed_visits; ?>, <?php echo $cancelled_visits; ?>],
                backgroundColor: ['rgba(0, 123, 255, 0.7)', 'rgba(40, 167, 69, 0.7)', 'rgba(220, 53, 69, 0.7)'],
                borderColor: ['rgba(0, 123, 255, 1)', 'rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>