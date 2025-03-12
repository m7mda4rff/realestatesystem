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
require_once '../../classes/Commission.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$commission = new Commission($conn);
$user = new User($conn);

// الحصول على قائمة مندوبي المبيعات للفلترة
$salespeople = $user->readAll(['role' => 'salesperson']);

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

// استعلام تقرير العمولات
// تعديل الاستعلام ليتوافق مع قاعدة البيانات
$sql = "SELECT 
            u.id as salesperson_id,
            u.full_name as salesperson_name,
            COUNT(c.id) as total_count,
            SUM(c.amount) as total_amount,
            SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END) as paid_amount,
            SUM(CASE WHEN c.status = 'pending' THEN c.amount ELSE 0 END) as pending_amount,
            COUNT(CASE WHEN c.status = 'paid' THEN 1 ELSE NULL END) as paid_count,
            COUNT(CASE WHEN c.status = 'pending' THEN 1 ELSE NULL END) as pending_count
        FROM 
            users u
        LEFT JOIN 
            commissions c ON u.id = c.salesperson_id AND DATE(c.created_at) BETWEEN ? AND ?
        WHERE 
            u.role = 'salesperson'";

// إضافة فلترة المندوب
if ($salesperson_filter > 0) {
    $sql .= " AND u.id = " . $salesperson_filter;
}

$sql .= " GROUP BY u.id, u.full_name ORDER BY total_amount DESC";

// تنفيذ الاستعلام
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$report_data = [];
$total_all_amount = 0;
$total_paid_amount = 0;
$total_pending_amount = 0;
$total_count = 0;

while ($row = $result->fetch_assoc()) {
    $report_data[] = $row;
    $total_all_amount += $row['total_amount'] ?? 0;
    $total_paid_amount += $row['paid_amount'] ?? 0;
    $total_pending_amount += $row['pending_amount'] ?? 0;
    $total_count += $row['total_count'] ?? 0;
}

// استعلام للحصول على معدل العمولات حسب الشهر
$monthly_sql = "SELECT 
                    DATE_FORMAT(c.created_at, '%Y-%m') as month,
                    SUM(c.amount) as total_amount,
                    SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END) as paid_amount
                FROM 
                    commissions c
                WHERE 
                    DATE(c.created_at) BETWEEN ? AND ?";

if ($salesperson_filter > 0) {
    $monthly_sql .= " AND c.salesperson_id = " . $salesperson_filter;
}

$monthly_sql .= " GROUP BY DATE_FORMAT(c.created_at, '%Y-%m')
                  ORDER BY month";

// تنفيذ الاستعلام
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->bind_param("ss", $start_date, $end_date);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();

$monthly_data = [];
$months = [];
$monthly_totals = [];
$monthly_paid = [];

while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
    $months[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_totals[] = $row['total_amount'] ?? 0;
    $monthly_paid[] = $row['paid_amount'] ?? 0;
}

// تعيين عنوان الصفحة
$page_title = 'تقارير العمولات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير العمولات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير العمولات</li>
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
                            <?php foreach ($salespeople as $person) : ?>
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
                        <div class="col-md-4">
                            <div class="card bg-primary text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">إجمالي العمولات</div>
                                            <div class="display-6"><?php echo formatMoney($total_all_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-coins fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">العمولات المدفوعة</div>
                                            <div class="display-6"><?php echo formatMoney($total_paid_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-check-circle fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">العمولات المعلقة</div>
                                            <div class="display-6"><?php echo formatMoney($total_pending_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-clock fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- رسم بياني للعمولات الشهرية -->
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    العمولات الشهرية
                                </div>
                                <div class="card-body">
                                    <canvas id="monthlyCommissionsChart" width="100%" height="50"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- رسم بياني لتوزيع حالة العمولات -->
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-chart-pie me-1"></i>
                                    توزيع حالة العمولات
                                </div>
                                <div class="card-body">
                                    <canvas id="commissionsStatusChart" width="100%" height="50"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- جدول بيانات التقرير -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            بيانات العمولات حسب المندوب
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المندوب</th>
                            <th>عدد العمولات</th>
                            <th>إجمالي العمولات</th>
                            <th>المدفوعة</th>
                            <th>قيد الانتظار</th>
                            <th>نسبة التحصيل</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data)) : ?>
                            <?php foreach ($report_data as $index => $row) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($row['salesperson_name']); ?></td>
                                    <td><?php echo $row['total_count'] ?? 0; ?></td>
                                    <td><?php echo formatMoney($row['total_amount'] ?? 0); ?></td>
                                    <td><?php echo formatMoney($row['paid_amount'] ?? 0); ?></td>
                                    <td><?php echo formatMoney($row['pending_amount'] ?? 0); ?></td>
                                    <td>
                                        <?php
                                            $payment_ratio = ($row['total_amount'] > 0) ? (($row['paid_amount'] ?? 0) / $row['total_amount'] * 100) : 0;
                                            echo number_format($payment_ratio, 1) . '%';
                                        ?>
                                    </td>
                                    <td>
                                        <a href="../commissions/index.php?salesperson_id=<?php echo $row['salesperson_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i> عرض التفاصيل
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">لا توجد بيانات متاحة للفترة المحددة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-dark">
                            <th colspan="2">الإجمالي</th>
                            <th><?php echo $total_count; ?></th>
                            <th><?php echo formatMoney($total_all_amount); ?></th>
                            <th><?php echo formatMoney($total_paid_amount); ?></th>
                            <th><?php echo formatMoney($total_pending_amount); ?></th>
                            <th>
                                <?php
                                    $total_payment_ratio = ($total_all_amount > 0) ? ($total_paid_amount / $total_all_amount * 100) : 0;
                                    echo number_format($total_payment_ratio, 1) . '%';
                                ?>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- جدول العمولات المدفوعة مؤخراً -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-money-bill-wave me-1"></i>
            آخر العمولات المدفوعة
        </div>
        <div class="card-body">
            <?php
            // استعلام لجلب آخر العمولات المدفوعة
            $recent_paid_sql = "SELECT c.*, s.amount as sale_amount, s.sale_date, u.full_name as salesperson_name, cl.name as client_name
                               FROM commissions c
                               LEFT JOIN sales s ON c.sale_id = s.id
                               LEFT JOIN users u ON c.salesperson_id = u.id
                               LEFT JOIN clients cl ON s.client_id = cl.id
                               WHERE c.status = 'paid'";
            
            if ($salesperson_filter > 0) {
                $recent_paid_sql .= " AND c.salesperson_id = " . $salesperson_filter;
            }
            
            $recent_paid_sql .= " ORDER BY c.payment_date DESC LIMIT 10";
            
            $recent_paid_result = $conn->query($recent_paid_sql);
            $recent_paid_commissions = [];
            
            if ($recent_paid_result && $recent_paid_result->num_rows > 0) {
                while ($row = $recent_paid_result->fetch_assoc()) {
                    $recent_paid_commissions[] = $row;
                }
            }
            ?>
            
            <?php if (!empty($recent_paid_commissions)) : ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>العميل</th>
                                <th>تاريخ البيع</th>
                                <th>قيمة العمولة</th>
                                <th>تاريخ الدفع</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_paid_commissions as $index => $comm) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($comm['salesperson_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($comm['client_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($comm['sale_date'] ?? 'now')); ?></td>
                                    <td><?php echo formatMoney($comm['amount']); ?></td>
                                    <td><?php echo $comm['payment_date'] ? date('Y-m-d', strtotime($comm['payment_date'])) : '-'; ?></td>
                                    <td>
                                        <a href="../commissions/view.php?id=<?php echo $comm['id']; ?>" class="btn btn-info btn-sm" title="عرض">
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
                    <i class="fas fa-info-circle me-1"></i> لا توجد عمولات مدفوعة مؤخراً للفترة المحددة.
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
    // الرسم البياني لحالة العمولات
    const statusCtx = document.getElementById('commissionsStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار'],
            datasets: [{
                data: [<?php echo $total_paid_amount; ?>, <?php echo $total_pending_amount; ?>],
                backgroundColor: ['#28a745', '#ffc107'],
                borderColor: ['#28a745', '#ffc107'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع حالة العمولات'
                }
            }
        }
    });
    
    // الرسم البياني للعمولات الشهرية
    const monthlyCtx = document.getElementById('monthlyCommissionsChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [
                {
                    label: 'إجمالي العمولات',
                    data: <?php echo json_encode($monthly_totals); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'العمولات المدفوعة',
                    data: <?php echo json_encode($monthly_paid); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'إحصائيات العمولات الشهرية'
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