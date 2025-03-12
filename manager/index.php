<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Sale.php';
require_once '../classes/User.php';
require_once '../classes/Target.php';
require_once '../classes/Visit.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);
$target = new Target($conn);
$visit = new Visit($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// الحصول على قائمة المندوبين التابعين للمدير
$salespeople = $user->getSalespeopleByManager($user_id);
$salespeople_ids = array_column($salespeople, 'id');

// الحصول على إحصائيات المبيعات للشهر الحالي
$current_month = date('Y-m');
$sales_stats = array();
$total_sales_amount = 0;
$total_sales_count = 0;
$total_commission = 0;

// حلقة للحصول على إحصائيات المبيعات لكل مندوب
foreach ($salespeople as $salesperson) {
    $salesperson_stats = $sale->getMonthlySales($salesperson['id'], $current_month);
    $sales_stats[$salesperson['id']] = $salesperson_stats;
    
    $total_sales_amount += $salesperson_stats['amount'];
    $total_sales_count += $salesperson_stats['count'];
    $total_commission += $salesperson_stats['commission'];
}

// الحصول على إحصائيات الأهداف للمندوبين
$targets_data = array();
$total_target_amount = 0;
$total_achieved_amount = 0;

foreach ($salespeople as $salesperson) {
    $current_target = $target->getCurrentTarget($salesperson['id']);
    $targets_data[$salesperson['id']] = $current_target;
    
    $total_target_amount += $current_target['target_amount'];
    $total_achieved_amount += $current_target['achieved_amount'];
}

// حساب نسبة تحقيق الأهداف الإجمالية
$target_percentage = calculateAchievement($total_achieved_amount, $total_target_amount);

// الحصول على إحصائيات الزيارات للشهر الحالي
$visits_stats = array();
$total_visits = 0;
$planned_visits = 0;
$completed_visits = 0;
$cancelled_visits = 0;

foreach ($salespeople as $salesperson) {
    $visit_stat = $visit->getVisitStats($salesperson['id'], 'month');
    $visits_stats[$salesperson['id']] = $visit_stat;
    
    $total_visits += $visit_stat['total'];
    $planned_visits += $visit_stat['planned'];
    $completed_visits += $visit_stat['completed'];
    $cancelled_visits += $visit_stat['cancelled'];
}

// الحصول على أحدث المبيعات للفريق
$filters = array(
    'salesperson_id' => $salespeople_ids,
    'limit' => 10
);

$recent_sales = $sale->readAll($filters);

// تعيين عنوان الصفحة
$page_title = 'لوحة تحكم مدير المبيعات';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">لوحة تحكم مدير المبيعات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">لوحة التحكم</li>
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
    
    <!-- بطاقات الملخص -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($total_sales_amount); ?></h4>
                            <div class="small">إجمالي المبيعات الشهرية</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-chart-line fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="sales/index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $target_percentage; ?>%</h4>
                            <div class="small">تحقيق الهدف الشهري</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-bullseye fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="targets/index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo count($salespeople); ?></h4>
                            <div class="small">عدد المندوبين</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="salespeople/index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $total_visits; ?></h4>
                            <div class="small">زيارات الشهر</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar-check fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="visits/index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- شريط التقدم للهدف الشهري -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bullseye me-1"></i>
                    تقدم تحقيق الهدف الشهري للفريق
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo formatMoney($total_achieved_amount); ?> من <?php echo formatMoney($total_target_amount); ?></h5>
                    <div class="progress" style="height: 25px;">
                        <?php $color = getColorByPercentage($target_percentage); ?>
                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo min($target_percentage, 100); ?>%;" aria-valuenow="<?php echo $target_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $target_percentage; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- مقارنة أداء المندوبين -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    مقارنة مبيعات المندوبين
                </div>
                <div class="card-body">
                    <canvas id="salesComparisonChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزيع الزيارات الشهرية
                </div>
                <div class="card-body">
                    <canvas id="visitsChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- أحدث المبيعات وأداء المندوبين -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    أحدث المبيعات
                </div>
                <div class="card-body">
                    <?php if (count($recent_sales) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المندوب</th>
                                        <th>العميل</th>
                                        <th>التاريخ</th>
                                        <th>المبلغ</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sales as $index => $sale_item) : ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                            <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                                    <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                                </span>
                                            </td>
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
                    <div class="text-center mt-3">
                        <a href="sales/index.php" class="btn btn-primary">عرض جميع المبيعات</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i>
                    أداء المندوبين
                </div>
                <div class="card-body">
                    <?php if (count($salespeople) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>المندوب</th>
                                        <th>المبيعات</th>
                                        <th>نسبة التحقيق</th>
                                        <th>الزيارات</th>
                                        <th>عرض</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salespeople as $index => $salesperson) : ?>
                                        <?php
                                            $sp_id = $salesperson['id'];
                                            $sp_sales = isset($sales_stats[$sp_id]) ? $sales_stats[$sp_id]['amount'] : 0;
                                            $sp_target = isset($targets_data[$sp_id]) ? $targets_data[$sp_id]['target_amount'] : 0;
                                            $sp_achieved = isset($targets_data[$sp_id]) ? $targets_data[$sp_id]['achieved_amount'] : 0;
                                            $sp_percentage = calculateAchievement($sp_achieved, $sp_target);
                                            $sp_visits = isset($visits_stats[$sp_id]) ? $visits_stats[$sp_id]['total'] : 0;
                                            $sp_color = getColorByPercentage($sp_percentage);
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($salesperson['full_name']); ?></td>
                                            <td><?php echo formatMoney($sp_sales); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $sp_color; ?>" role="progressbar" style="width: <?php echo min($sp_percentage, 100); ?>%;" aria-valuenow="<?php echo $sp_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $sp_percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $sp_visits; ?></td>
                                            <td>
                                                <a href="salespeople/view.php?id=<?php echo $sp_id; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> لا يوجد مندوبين تحت إدارتك.
                        </div>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="salespeople/index.php" class="btn btn-primary">إدارة المندوبين</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // بيانات مقارنة مبيعات المندوبين
    var salesCtx = document.getElementById('salesComparisonChart').getContext('2d');
    var salesChart = new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($salespeople as $salesperson) : ?>
                    '<?php echo htmlspecialchars($salesperson['full_name']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'المبيعات الشهرية (ج.م)',
                data: [
                    <?php foreach ($salespeople as $salesperson) : ?>
                        <?php echo isset($sales_stats[$salesperson['id']]) ? $sales_stats[$salesperson['id']]['amount'] : 0; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
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
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>