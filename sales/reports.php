<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومندوب مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Sale.php';
require_once '../classes/Target.php';
require_once '../classes/Commission.php';
require_once '../classes/Visit.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$target = new Target($conn);
$commission = new Commission($conn);
$visit = new Visit($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// تحديد الفترة الزمنية (افتراضياً الشهر الحالي)
$period = isset($_GET['period']) ? $_GET['period'] : 'month';

// تحديد تاريخ البداية والنهاية للفلترة
$start_date = '';
$end_date = '';

switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $start_date = date('Y-' . (($quarter - 1) * 3 + 1) . '-01');
        $end_date = date('Y-m-t', strtotime($start_date . ' +2 month'));
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        break;
}

// الحصول على إحصائيات المبيعات للفترة المحددة
$sales_filters = [
    'salesperson_id' => $user_id,
    'start_date' => $start_date,
    'end_date' => $end_date
];
$sales = $sale->readAll($sales_filters);

// حساب إجماليات المبيعات
$total_sales_amount = 0;
$total_commission_amount = 0;
$sales_by_status = [
    'paid' => 0,
    'pending' => 0,
    'cancelled' => 0
];

foreach ($sales as $sale_item) {
    $total_sales_amount += $sale_item['amount'];
    $total_commission_amount += $sale_item['commission_amount'];
    $sales_by_status[$sale_item['payment_status']]++;
}

// الحصول على الأهداف
$targets = $target->getTargetsBySalesperson($user_id);

// الحصول على إحصائيات الزيارات
$visits_filters = [
    'salesperson_id' => $user_id,
    'start_date' => $start_date,
    'end_date' => $end_date
];
$visits = $visit->readAll($visits_filters);

// حساب إحصائيات الزيارات
$total_visits = count($visits);
$visits_by_status = [
    'planned' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($visits as $visit_item) {
    $visits_by_status[$visit_item['visit_status']]++;
}

// الحصول على بيانات المخطط للمبيعات
$chart_data = $sale->getChartData($period === 'month' ? 'month' : 'year');

// تعيين عنوان الصفحة
$page_title = 'تقارير الأداء';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير الأداء</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير الأداء</li>
    </ol>
    
    <!-- فلتر الفترة الزمنية -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            تحديد الفترة الزمنية
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row align-items-end">
                <div class="col-md-4 mb-3">
                    <label for="period" class="form-label">الفترة</label>
                    <select class="form-select" id="period" name="period" onchange="toggleCustomDateInputs()">
                        <option value="week" <?php echo ($period === 'week') ? 'selected' : ''; ?>>الأسبوع الحالي</option>
                        <option value="month" <?php echo ($period === 'month') ? 'selected' : ''; ?>>الشهر الحالي</option>
                        <option value="quarter" <?php echo ($period === 'quarter') ? 'selected' : ''; ?>>الربع الحالي</option>
                        <option value="year" <?php echo ($period === 'year') ? 'selected' : ''; ?>>السنة الحالية</option>
                        <option value="custom" <?php echo ($period === 'custom') ? 'selected' : ''; ?>>فترة مخصصة</option>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3 custom-date-inputs" style="<?php echo ($period !== 'custom') ? 'display: none;' : ''; ?>">
                    <label for="start_date" class="form-label">من تاريخ</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3 mb-3 custom-date-inputs" style="<?php echo ($period !== 'custom') ? 'display: none;' : ''; ?>">
                    <label for="end_date" class="form-label">إلى تاريخ</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-2 mb-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> عرض
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ملخص الأداء -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($total_sales_amount); ?></h4>
                            <div class="small">إجمالي المبيعات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-chart-line fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mysales.php">عرض المبيعات</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($total_commission_amount); ?></h4>
                            <div class="small">إجمالي العمولات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-coins fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mycommissions.php">عرض العمولات</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo count($sales); ?></h4>
                            <div class="small">عدد المبيعات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-shopping-cart fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mysales.php">عرض المبيعات</a>
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
                            <div class="small">عدد الزيارات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar-check fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="visits/index.php">عرض الزيارات</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تقارير المبيعات والزيارات -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    تقرير المبيعات
                </div>
                <div class="card-body">
                    <canvas id="salesChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    تقرير الزيارات
                </div>
                <div class="card-body">
                    <canvas id="visitsChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تقرير الأهداف -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bullseye me-1"></i>
                    تقرير الأهداف والإنجازات
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>الفترة</th>
                                    <th>الهدف</th>
                                    <th>المحقق</th>
                                    <th>نسبة الإنجاز</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($targets) > 0) : ?>
                                    <?php foreach ($targets as $target_item) : ?>
                                        <?php
                                            $achievement_percentage = calculateAchievement($target_item['achieved_amount'], $target_item['target_amount']);
                                            $status_color = getColorByPercentage($achievement_percentage);
                                            $status_text = $achievement_percentage >= 100 ? 'مكتمل' : 'قيد التنفيذ';
                                        ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d', strtotime($target_item['start_date'])) . ' إلى ' . date('Y-m-d', strtotime($target_item['end_date'])); ?></td>
                                            <td><?php echo formatMoney($target_item['target_amount']); ?></td>
                                            <td><?php echo formatMoney($target_item['achieved_amount']); ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo $achievement_percentage; ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $achievement_percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $status_color; ?>"><?php echo $status_text; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center">لا توجد أهداف متاحة</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تقرير المبيعات حسب الحالة -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    المبيعات حسب الحالة
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>الحالة</th>
                                    <th>العدد</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge status-paid">مدفوعة</span></td>
                                    <td><?php echo $sales_by_status['paid']; ?></td>
                                    <td><?php echo count($sales) > 0 ? round(($sales_by_status['paid'] / count($sales)) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge status-pending">قيد الانتظار</span></td>
                                    <td><?php echo $sales_by_status['pending']; ?></td>
                                    <td><?php echo count($sales) > 0 ? round(($sales_by_status['pending'] / count($sales)) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge status-cancelled">ملغية</span></td>
                                    <td><?php echo $sales_by_status['cancelled']; ?></td>
                                    <td><?php echo count($sales) > 0 ? round(($sales_by_status['cancelled'] / count($sales)) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-check me-1"></i>
                    الزيارات حسب الحالة
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>الحالة</th>
                                    <th>العدد</th>
                                    <th>النسبة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge visit-planned">مخططة</span></td>
                                    <td><?php echo $visits_by_status['planned']; ?></td>
                                    <td><?php echo $total_visits > 0 ? round(($visits_by_status['planned'] / $total_visits) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge visit-completed">مكتملة</span></td>
                                    <td><?php echo $visits_by_status['completed']; ?></td>
                                    <td><?php echo $total_visits > 0 ? round(($visits_by_status['completed'] / $total_visits) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge visit-cancelled">ملغية</span></td>
                                    <td><?php echo $visits_by_status['cancelled']; ?></td>
                                    <td><?php echo $total_visits > 0 ? round(($visits_by_status['cancelled'] / $total_visits) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// وظيفة لإظهار/إخفاء حقول التاريخ المخصصة
function toggleCustomDateInputs() {
    var periodSelect = document.getElementById('period');
    var customDateInputs = document.querySelectorAll('.custom-date-inputs');
    
    if (periodSelect.value === 'custom') {
        customDateInputs.forEach(function(input) {
            input.style.display = 'block';
        });
    } else {
        customDateInputs.forEach(function(input) {
            input.style.display = 'none';
        });
    }
}

// تهيئة مخطط المبيعات
var salesCtx = document.getElementById('salesChart').getContext('2d');
var salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: ['مدفوعة', 'قيد الانتظار', 'ملغية'],
        datasets: [{
            label: 'عدد المبيعات',
            data: [
                <?php echo $sales_by_status['paid']; ?>,
                <?php echo $sales_by_status['pending']; ?>,
                <?php echo $sales_by_status['cancelled']; ?>
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.7)',
                'rgba(255, 193, 7, 0.7)',
                'rgba(220, 53, 69, 0.7)'
            ],
            borderColor: [
                'rgba(40, 167, 69, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(220, 53, 69, 1)'
            ],
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

// تهيئة مخطط الزيارات
var visitsCtx = document.getElementById('visitsChart').getContext('2d');
var visitsChart = new Chart(visitsCtx, {
    type: 'pie',
    data: {
        labels: ['مخططة', 'مكتملة', 'ملغية'],
        datasets: [{
            data: [
                <?php echo $visits_by_status['planned']; ?>,
                <?php echo $visits_by_status['completed']; ?>,
                <?php echo $visits_by_status['cancelled']; ?>
            ],
            backgroundColor: [
                'rgba(0, 123, 255, 0.7)',
                'rgba(40, 167, 69, 0.7)',
                'rgba(220, 53, 69, 0.7)'
            ],
            borderColor: [
                'rgba(0, 123, 255, 1)',
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)'
            ],
            borderWidth: 1
        }]
    }
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>