<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير نظام
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// الحصول على قائمة المندوبين
$salespeople = $user->readAll(['role' => 'salesperson']);

// الحصول على قائمة العملاء
$clients = $client->readAll();

// إعدادات الفلترة
$filters = [];

// فلترة حسب المندوب
if (isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id'])) {
    $filters['salesperson_id'] = (int)$_GET['salesperson_id'];
}

// فلترة حسب العميل
if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $filters['client_id'] = (int)$_GET['client_id'];
}

// فلترة حسب حالة الدفع
if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
    $filters['payment_status'] = $_GET['payment_status'];
}

// فلترة حسب الفترة الزمنية
if (isset($_GET['period']) && !empty($_GET['period'])) {
    $period = $_GET['period'];
    
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            $filters['start_date'] = $today;
            $filters['end_date'] = $today;
            break;
        case 'week':
            $filters['start_date'] = date('Y-m-d', strtotime('monday this week'));
            $filters['end_date'] = date('Y-m-d', strtotime('sunday this week'));
            break;
        case 'month':
            $filters['start_date'] = date('Y-m-01');
            $filters['end_date'] = date('Y-m-t');
            break;
        case 'quarter':
            $quarter = ceil(date('n') / 3);
            $filters['start_date'] = date('Y-' . (($quarter - 1) * 3 + 1) . '-01');
            $filters['end_date'] = date('Y-m-t', strtotime($filters['start_date'] . ' +2 month'));
            break;
        case 'year':
            $filters['start_date'] = date('Y-01-01');
            $filters['end_date'] = date('Y-12-31');
            break;
        case 'custom':
            if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }
            break;
    }
}
// إذا كانت فترة مخصصة
else {
    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }
    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }
}

// البحث
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// الحصول على قائمة المبيعات
$sales_list = $sale->readAll($filters);

// حساب إجماليات المبيعات
$total_amount = 0;
$total_commission = 0;
$paid_amount = 0;
$pending_amount = 0;
$cancelled_amount = 0;

foreach ($sales_list as $sale_item) {
    $total_amount += $sale_item['amount'];
    $total_commission += $sale_item['commission_amount'];
    
    if ($sale_item['payment_status'] === 'paid') {
        $paid_amount += $sale_item['amount'];
    } elseif ($sale_item['payment_status'] === 'pending') {
        $pending_amount += $sale_item['amount'];
    } elseif ($sale_item['payment_status'] === 'cancelled') {
        $cancelled_amount += $sale_item['amount'];
    }
}

// الحصول على إحصائيات المبيعات حسب المندوب
$sales_by_salesperson = $sale->getSalesBySalespersonStats(isset($_GET['period']) && !empty($_GET['period']) ? $_GET['period'] : 'all');

// الحصول على إحصائيات المبيعات حسب الشهر للرسم البياني
$chart_period = (isset($_GET['period']) && $_GET['period'] === 'year') ? 'year' : 'month';
$chart_data = $sale->getChartData($chart_period);

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
    
    <!-- بطاقة فلتر التقرير -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلتر التقرير
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="salesperson_id" class="form-label">مندوب المبيعات</label>
                        <select class="form-select select2" id="salesperson_id" name="salesperson_id">
                            <option value="">الكل</option>
                            <?php foreach ($salespeople as $sp) : ?>
                                <option value="<?php echo $sp['id']; ?>" <?php echo (isset($_GET['salesperson_id']) && $_GET['salesperson_id'] == $sp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="client_id" class="form-label">العميل</label>
                        <select class="form-select select2" id="client_id" name="client_id">
                            <option value="">الكل</option>
                            <?php foreach ($clients as $client_item) : ?>
                                <option value="<?php echo $client_item['id']; ?>" <?php echo (isset($_GET['client_id']) && $_GET['client_id'] == $client_item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client_item['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="payment_status" class="form-label">حالة الدفع</label>
                        <select class="form-select" id="payment_status" name="payment_status">
                            <option value="">الكل</option>
                            <option value="paid" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                            <option value="pending" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                            <option value="cancelled" <?php echo (isset($_GET['payment_status']) && $_GET['payment_status'] === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="period" class="form-label">الفترة الزمنية</label>
                        <select class="form-select" id="period" name="period" onchange="toggleCustomDateFields()">
                            <option value="">اختر الفترة</option>
                            <option value="today" <?php echo (isset($_GET['period']) && $_GET['period'] === 'today') ? 'selected' : ''; ?>>اليوم</option>
                            <option value="week" <?php echo (isset($_GET['period']) && $_GET['period'] === 'week') ? 'selected' : ''; ?>>الأسبوع الحالي</option>
                            <option value="month" <?php echo (isset($_GET['period']) && $_GET['period'] === 'month') ? 'selected' : ''; ?>>الشهر الحالي</option>
                            <option value="quarter" <?php echo (isset($_GET['period']) && $_GET['period'] === 'quarter') ? 'selected' : ''; ?>>الربع الحالي</option>
                            <option value="year" <?php echo (isset($_GET['period']) && $_GET['period'] === 'year') ? 'selected' : ''; ?>>السنة الحالية</option>
                            <option value="custom" <?php echo (isset($_GET['period']) && $_GET['period'] === 'custom') ? 'selected' : ''; ?>>فترة مخصصة</option>
                        </select>
                    </div>
                </div>
                <div class="row custom-date-fields" id="customDateFields" style="<?php echo (isset($_GET['period']) && $_GET['period'] === 'custom') ? '' : 'display: none;'; ?>">
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="search" class="form-label">بحث</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="ابحث...">
                    </div>
                    <div class="col-md-6 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> عرض التقرير
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="btn btn-secondary me-2">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                        <button type="button" class="btn btn-success" onclick="printReport()">
                            <i class="fas fa-print me-1"></i> طباعة التقرير
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ملخص التقرير -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($total_amount); ?></h4>
                            <div class="small">إجمالي المبيعات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-shopping-cart fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">عدد المبيعات: <?php echo count($sales_list); ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($paid_amount); ?></h4>
                            <div class="small">المبيعات المدفوعة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">نسبة المدفوع: <?php echo $total_amount > 0 ? round(($paid_amount / $total_amount) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($pending_amount); ?></h4>
                            <div class="small">المبيعات المعلقة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">نسبة المعلق: <?php echo $total_amount > 0 ? round(($pending_amount / $total_amount) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($total_commission); ?></h4>
                            <div class="small">إجمالي العمولات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-coins fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">متوسط العمولة: <?php echo $total_amount > 0 ? round(($total_commission / $total_amount) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الرسوم البيانية -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    المبيعات خلال الفترة
                </div>
                <div class="card-body">
                    <canvas id="salesChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزيع المبيعات حسب الحالة
                </div>
                <div class="card-body">
                    <canvas id="statusChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- جدول المبيعات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            تفاصيل المبيعات
        </div>
        <div class="card-body">
            <?php if (count($sales_list) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable" id="salesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>المندوب</th>
                                <th>المبلغ</th>
                                <th>العمولة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_list as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                    <td><?php echo formatMoney($sale_item['commission_amount']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                            <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4">الإجمالي</th>
                                <th><?php echo formatMoney($total_amount); ?></th>
                                <th><?php echo formatMoney($total_commission); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد بيانات للعرض وفقاً للفلتر المحدد.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- تقرير المبيعات حسب المندوب -->
    <?php if (count($sales_by_salesperson) > 0) : ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-users me-1"></i>
                المبيعات حسب المندوب
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>عدد المبيعات</th>
                                <th>إجمالي المبيعات</th>
                                <th>إجمالي العمولات</th>
                                <th>متوسط قيمة المبيعة</th>
                                <th>نسبة المساهمة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_by_salesperson as $index => $sp_stats) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($sp_stats['salesperson_name']); ?></td>
                                    <td><?php echo $sp_stats['count']; ?></td>
                                    <td><?php echo formatMoney($sp_stats['amount']); ?></td>
                                    <td><?php echo formatMoney($sp_stats['commission']); ?></td>
                                    <td>
                                        <?php echo $sp_stats['count'] > 0 ? formatMoney($sp_stats['amount'] / $sp_stats['count']) : formatMoney(0); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $contribution = $total_amount > 0 ? round(($sp_stats['amount'] / $total_amount) * 100, 1) : 0;
                                            echo $contribution . '%';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- رسم بياني لتوزيع المبيعات حسب المندوب -->
                <div class="mt-4">
                    <canvas id="salesBySalespersonChart" width="100%" height="300"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript للتقرير -->
<script>
// إظهار/إخفاء حقول التاريخ المخصصة
function toggleCustomDateFields() {
    const periodSelect = document.getElementById('period');
    const customDateFields = document.getElementById('customDateFields');
    
    if (periodSelect.value === 'custom') {
        customDateFields.style.display = 'flex';
    } else {
        customDateFields.style.display = 'none';
    }
}

// طباعة التقرير
function printReport() {
    window.print();
}

// إنشاء الرسوم البيانية
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني للمبيعات خلال الفترة
    var salesCtx = document.getElementById('salesChart').getContext('2d');
    var salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: [
                <?php foreach ($chart_data as $data_point) : ?>
                    '<?php echo $data_point['date_label']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'المبيعات',
                data: [
                    <?php foreach ($chart_data as $data_point) : ?>
                        <?php echo $data_point['total_amount']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'المبيعات خلال الفترة'
                }
            }
        }
    });
    
    // رسم بياني لتوزيع المبيعات حسب الحالة
    var statusCtx = document.getElementById('statusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار', 'ملغية'],
            datasets: [{
                data: [
                    <?php echo $paid_amount; ?>,
                    <?php echo $pending_amount; ?>,
                    <?php echo $cancelled_amount; ?>
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
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع المبيعات حسب الحالة'
                }
            }
        }
    });
    
    <?php if (count($sales_by_salesperson) > 0) : ?>
    // رسم بياني لتوزيع المبيعات حسب المندوب
    var salespersonCtx = document.getElementById('salesBySalespersonChart').getContext('2d');
    var salespersonChart = new Chart(salespersonCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($sales_by_salesperson as $sp_stats) : ?>
                    '<?php echo htmlspecialchars($sp_stats['salesperson_name']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'إجمالي المبيعات',
                data: [
                    <?php foreach ($sales_by_salesperson as $sp_stats) : ?>
                        <?php echo $sp_stats['amount']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'إجمالي العمولات',
                data: [
                    <?php foreach ($sales_by_salesperson as $sp_stats) : ?>
                        <?php echo $sp_stats['commission']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع المبيعات والعمولات حسب المندوب'
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>