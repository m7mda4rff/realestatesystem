<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Sale.php';
require_once '../../classes/User.php';
require_once '../../classes/Client.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);
$client = new Client($conn);

// الحصول على المندوبين التابعين للمدير الحالي
$salespersons = $user->getSalespeopleByManager($_SESSION['user_id']);

// تحديد فترة التقرير
$report_period = isset($_GET['period']) ? $_GET['period'] : 'month';
$valid_periods = ['week', 'month', 'quarter', 'year', 'custom'];

if (!in_array($report_period, $valid_periods)) {
    $report_period = 'month';
}

// تحديد تواريخ البداية والنهاية للتقرير
$start_date = '';
$end_date = '';

if ($report_period === 'custom') {
    // فترة مخصصة
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
} else {
    // فترات محددة مسبقاً
    switch ($report_period) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            break;
        case 'quarter':
            $current_month = date('n');
            $current_quarter = ceil($current_month / 3);
            $start_month = ($current_quarter - 1) * 3 + 1;
            $end_month = $current_quarter * 3;
            $start_date = date('Y-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $end_date = date('Y-' . str_pad($end_month, 2, '0', STR_PAD_LEFT) . '-' . date('t', strtotime($start_date)));
            break;
        case 'year':
            $start_date = date('Y-01-01');
            $end_date = date('Y-12-31');
            break;
    }
}

// الحصول على فلترة المندوب
$salesperson_filter = isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id']) ? (int)$_GET['salesperson_id'] : 0;

// تحقق من أن المندوب تابع للمدير الحالي
$is_valid_salesperson = false;
if ($salesperson_filter > 0) {
    foreach ($salespersons as $person) {
        if ($person['id'] == $salesperson_filter) {
            $is_valid_salesperson = true;
            break;
        }
    }
    
    if (!$is_valid_salesperson) {
        $salesperson_filter = 0;
    }
}

// إعداد فلاتر التقرير
$filters = [];
$filters['start_date'] = $start_date;
$filters['end_date'] = $end_date;

// إضافة فلتر المندوب إذا تم تحديده
if ($salesperson_filter > 0) {
    $filters['salesperson_id'] = $salesperson_filter;
} else {
    // إذا لم يتم تحديد مندوب، قم بإنشاء قائمة بمعرفات جميع المندوبين التابعين للمدير
    $salesperson_ids = array_map(function($person) {
        return $person['id'];
    }, $salespersons);
    
    if (!empty($salesperson_ids)) {
        $filters['salesperson_ids'] = $salesperson_ids;
    }
}

// الحصول على بيانات المبيعات
$sales = $sale->readAll($filters);

// حساب إجماليات المبيعات
$total_sales = 0;
$total_commissions = 0;
$paid_sales = 0;
$pending_sales = 0;
$cancelled_sales = 0;
$sales_count = count($sales);

foreach ($sales as $sale_item) {
    $total_sales += $sale_item['amount'];
    $total_commissions += $sale_item['commission_amount'];
    
    switch ($sale_item['payment_status']) {
        case 'paid':
            $paid_sales += $sale_item['amount'];
            break;
        case 'pending':
            $pending_sales += $sale_item['amount'];
            break;
        case 'cancelled':
            $cancelled_sales += $sale_item['amount'];
            break;
    }
}

// الحصول على بيانات المبيعات حسب المندوب
$sales_by_salesperson = [];
foreach ($salespersons as $person) {
    $sales_by_salesperson[$person['id']] = [
        'name' => $person['full_name'],
        'total_amount' => 0,
        'total_commission' => 0,
        'sales_count' => 0
    ];
}

foreach ($sales as $sale_item) {
    if (isset($sales_by_salesperson[$sale_item['salesperson_id']])) {
        $sales_by_salesperson[$sale_item['salesperson_id']]['total_amount'] += $sale_item['amount'];
        $sales_by_salesperson[$sale_item['salesperson_id']]['total_commission'] += $sale_item['commission_amount'];
        $sales_by_salesperson[$sale_item['salesperson_id']]['sales_count']++;
    }
}

// ترتيب البيانات حسب المبيعات
usort($sales_by_salesperson, function($a, $b) {
    return $b['total_amount'] - $a['total_amount'];
});

// الحصول على بيانات الرسم البياني
$chart_data = $sale->getChartData($report_period);

// تعيين عنوان الصفحة
$page_title = 'تقارير المبيعات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير المبيعات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير المبيعات</li>
    </ol>
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            خيارات التقرير
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="period" class="form-label">الفترة</label>
                        <select class="form-select" id="period" name="period" onchange="toggleCustomDates()">
                            <option value="week" <?php echo ($report_period === 'week') ? 'selected' : ''; ?>>هذا الأسبوع</option>
                            <option value="month" <?php echo ($report_period === 'month') ? 'selected' : ''; ?>>هذا الشهر</option>
                            <option value="quarter" <?php echo ($report_period === 'quarter') ? 'selected' : ''; ?>>هذا الربع</option>
                            <option value="year" <?php echo ($report_period === 'year') ? 'selected' : ''; ?>>هذا العام</option>
                            <option value="custom" <?php echo ($report_period === 'custom') ? 'selected' : ''; ?>>فترة مخصصة</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3 custom-date-range" <?php echo ($report_period !== 'custom') ? 'style="display: none;"' : ''; ?>>
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3 mb-3 custom-date-range" <?php echo ($report_period !== 'custom') ? 'style="display: none;"' : ''; ?>>
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="salesperson_id" class="form-label">المندوب</label>
                        <select class="form-select" id="salesperson_id" name="salesperson_id">
                            <option value="">جميع المندوبين</option>
                            <?php foreach ($salespersons as $person) : ?>
                                <option value="<?php echo $person['id']; ?>" <?php echo ($salesperson_filter == $person['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($person['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> عرض التقرير
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ملخص التقرير -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-chart-line me-1"></i>
                        ملخص التقرير
                    </div>
                    <div>
                        من <?php echo formatDate($start_date); ?> إلى <?php echo formatDate($end_date); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">إجمالي المبيعات</div>
                                            <div class="display-6"><?php echo formatMoney($total_sales); ?></div>
                                        </div>
                                        <div><i class="fas fa-shopping-cart fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">المبيعات المدفوعة</div>
                                            <div class="display-6"><?php echo formatMoney($paid_sales); ?></div>
                                        </div>
                                        <div><i class="fas fa-check-circle fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">المبيعات المعلقة</div>
                                            <div class="display-6"><?php echo formatMoney($pending_sales); ?></div>
                                        </div>
                                        <div><i class="fas fa-clock fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">المبيعات الملغاة</div>
                                            <div class="display-6"><?php echo formatMoney($cancelled_sales); ?></div>
                                        </div>
                                        <div><i class="fas fa-times-circle fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="salesStatusChart" width="100%" height="50"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="monthlySalesChart" width="100%" height="50"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- المبيعات حسب المندوب -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-tie me-1"></i>
            المبيعات حسب المندوب
        </div>
        <div class="card-body">
            <?php if (count($sales_by_salesperson) > 0) : ?>
                <canvas id="salesBySalespersonChart" width="100%" height="40"></canvas>
                
                <div class="table-responsive mt-4">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>عدد المبيعات</th>
                                <th>إجمالي المبيعات</th>
                                <th>إجمالي العمولات</th>
                                <th>متوسط قيمة المبيعة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_by_salesperson as $index => $person_data) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($person_data['name']); ?></td>
                                    <td><?php echo $person_data['sales_count']; ?></td>
                                    <td><?php echo formatMoney($person_data['total_amount']); ?></td>
                                    <td><?php echo formatMoney($person_data['total_commission']); ?></td>
                                    <td><?php echo $person_data['sales_count'] > 0 ? formatMoney($person_data['total_amount'] / $person_data['sales_count']) : formatMoney(0); ?></td>
                                    <td>
                                        <a href="sales.php?period=<?php echo $report_period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&salesperson_id=<?php echo array_keys($sales_by_salesperson)[$index]; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-search me-1"></i> عرض المبيعات
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark">
                                <th colspan="2">الإجمالي</th>
                                <th><?php echo $sales_count; ?></th>
                                <th><?php echo formatMoney($total_sales); ?></th>
                                <th><?php echo formatMoney($total_commissions); ?></th>
                                <th><?php echo $sales_count > 0 ? formatMoney($total_sales / $sales_count) : formatMoney(0); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد بيانات متاحة للفترة المحددة
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- تفاصيل المبيعات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list me-1"></i>
            تفاصيل المبيعات
        </div>
        <div class="card-body">
            <?php if (count($sales) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>العميل</th>
                                <th>تاريخ البيع</th>
                                <th>المبلغ</th>
                                <th>العمولة</th>
                                <th>الحالة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                    <td><?php echo formatDate($sale_item['sale_date']); ?></td>
                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                    <td><?php echo formatMoney($sale_item['commission_amount']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                            <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../sales/view.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-sm btn-info">
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
                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات متاحة للفترة المحددة
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// تبديل حقول التاريخ المخصصة
function toggleCustomDates() {
    const periodSelect = document.getElementById('period');
    const customDateFields = document.querySelectorAll('.custom-date-range');
    
    if (periodSelect.value === 'custom') {
        customDateFields.forEach(field => field.style.display = 'block');
    } else {
        customDateFields.forEach(field => field.style.display = 'none');
    }
}

// إعداد البيانات للرسوم البيانية
document.addEventListener('DOMContentLoaded', function() {
    // الرسم البياني لحالة المبيعات
    const statusCtx = document.getElementById('salesStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار', 'ملغية'],
            datasets: [{
                data: [
                    <?php echo $paid_sales; ?>, 
                    <?php echo $pending_sales; ?>, 
                    <?php echo $cancelled_sales; ?>
                ],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع حالة المبيعات'
                }
            }
        }
    });
    
    // الرسم البياني للمبيعات الشهرية
    const monthlyCtx = document.getElementById('monthlySalesChart').getContext('2d');
    const monthlySalesChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                    $labels = [];
                    $amounts = [];
                    
                    foreach ($chart_data as $data) {
                        $labels[] = "'" . $data['date_label'] . "'";
                        $amounts[] = $data['total_amount'];
                    }
                    
                    echo implode(', ', $labels);
                ?>
            ],
            datasets: [{
                label: 'المبيعات',
                data: [
                    <?php echo implode(', ', $amounts); ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'المبيعات خلال الفترة'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // الرسم البياني للمبيعات حسب المندوب
    const salespersonCtx = document.getElementById('salesBySalespersonChart').getContext('2d');
    const salespersonChart = new Chart(salespersonCtx, {
        type: 'horizontalBar',
        data: {
            labels: [
                <?php 
                    $names = [];
                    $sales_amounts = [];
                    
                    foreach ($sales_by_salesperson as $person_data) {
                        $names[] = "'" . $person_data['name'] . "'";
                        $sales_amounts[] = $person_data['total_amount'];
                    }
                    
                    echo implode(', ', $names);
                ?>
            ],
            datasets: [{
                label: 'المبيعات',
                data: [
                    <?php echo implode(', ', $sales_amounts); ?>
                ],
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'المبيعات حسب المندوب'
                }
            },
            scales: {
                x: {
                    beginAtZero: true
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