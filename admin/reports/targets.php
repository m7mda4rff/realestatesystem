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

// الحصول على قائمة مندوبي المبيعات للفلترة
$salespeople = $user->readAll(['role' => 'salesperson']);

// تحديد العام المختار
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_year = date('Y');
$available_years = [];

// إعداد قائمة السنوات المتاحة (السنة الحالية والسنتين السابقتين)
for ($i = $current_year - 2; $i <= $current_year; $i++) {
    $available_years[] = $i;
}

// تحديد المندوب المختار
$salesperson_filter = isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id']) ? (int)$_GET['salesperson_id'] : 0;

// تحديد نوع العرض
$view_type = isset($_GET['view']) && in_array($_GET['view'], ['summary', 'detailed']) ? $_GET['view'] : 'summary';

// استعلام الأهداف
$sql = "SELECT t.*, u.full_name as salesperson_name
        FROM targets t
        LEFT JOIN users u ON t.salesperson_id = u.id
        WHERE YEAR(t.start_date) = ? OR YEAR(t.end_date) = ?";

if ($salesperson_filter > 0) {
    $sql .= " AND t.salesperson_id = ?";
}

$sql .= " ORDER BY t.end_date DESC";

// تنفيذ الاستعلام
$stmt = $conn->prepare($sql);

if ($salesperson_filter > 0) {
    $stmt->bind_param("iii", $selected_year, $selected_year, $salesperson_filter);
} else {
    $stmt->bind_param("ii", $selected_year, $selected_year);
}

$stmt->execute();
$result = $stmt->get_result();

$targets = [];
while ($row = $result->fetch_assoc()) {
    $targets[] = $row;
}

// إعداد بيانات التقرير
$total_targets = count($targets);
$total_target_amount = 0;
$total_achieved_amount = 0;
$completed_targets = 0;
$in_progress_targets = 0;
$future_targets = 0;

// معالجة البيانات للإحصائيات وللرسوم البيانية
$monthly_data = [];
$quarterly_data = [];
$salesperson_data = [];

// للتتبع حسب المندوب
$salesperson_targets = [];
$salesperson_achieved = [];
$salesperson_names = [];

$today = date('Y-m-d');

foreach ($targets as $t) {
    // حساب المجاميع الكلية
    $total_target_amount += $t['target_amount'];
    $total_achieved_amount += $t['achieved_amount'];
    
    // تحديد حالة الهدف (منتهي، حالي، مستقبلي)
    if ($today > $t['end_date']) {
        $completed_targets++;
    } elseif ($today >= $t['start_date'] && $today <= $t['end_date']) {
        $in_progress_targets++;
    } else {
        $future_targets++;
    }
    
    // جمع البيانات حسب المندوب
    if (!isset($salesperson_targets[$t['salesperson_id']])) {
        $salesperson_targets[$t['salesperson_id']] = 0;
        $salesperson_achieved[$t['salesperson_id']] = 0;
        $salesperson_names[$t['salesperson_id']] = $t['salesperson_name'];
    }
    
    $salesperson_targets[$t['salesperson_id']] += $t['target_amount'];
    $salesperson_achieved[$t['salesperson_id']] += $t['achieved_amount'];
    
    // جمع البيانات الشهرية أو الربعية
    $start_month = date('m', strtotime($t['start_date']));
    $end_month = date('m', strtotime($t['end_date']));
    $start_quarter = ceil($start_month / 3);
    $end_quarter = ceil($end_month / 3);
    
    // إضافة البيانات الشهرية
    for ($m = 1; $m <= 12; $m++) {
        $month_key = sprintf("%02d", $m);
        if (!isset($monthly_data[$month_key])) {
            $monthly_data[$month_key] = [
                'target' => 0,
                'achieved' => 0
            ];
        }
        
        // إذا كان الهدف يشمل هذا الشهر
        if (($start_month <= $m && $end_month >= $m) || 
            ($start_month > $end_month && ($m >= $start_month || $m <= $end_month))) {
            $monthly_data[$month_key]['target'] += $t['target_amount'] / max(1, abs($end_month - $start_month) + 1);
            $monthly_data[$month_key]['achieved'] += $t['achieved_amount'] / max(1, abs($end_month - $start_month) + 1);
        }
    }
    
    // إضافة البيانات الربعية
    for ($q = 1; $q <= 4; $q++) {
        if (!isset($quarterly_data[$q])) {
            $quarterly_data[$q] = [
                'target' => 0,
                'achieved' => 0
            ];
        }
        
        // إذا كان الهدف يشمل هذا الربع
        if (($start_quarter <= $q && $end_quarter >= $q) || 
            ($start_quarter > $end_quarter && ($q >= $start_quarter || $q <= $end_quarter))) {
            $quarterly_data[$q]['target'] += $t['target_amount'] / max(1, abs($end_quarter - $start_quarter) + 1);
            $quarterly_data[$q]['achieved'] += $t['achieved_amount'] / max(1, abs($end_quarter - $start_quarter) + 1);
        }
    }
}

// تنسيق البيانات للرسوم البيانية
$chart_months = ['يناير', 'فبراير', 'مارس', 'إبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
$chart_quarters = ['الربع الأول', 'الربع الثاني', 'الربع الثالث', 'الربع الرابع'];

$monthly_labels = [];
$monthly_targets = [];
$monthly_achieved = [];

$quarterly_labels = [];
$quarterly_targets = [];
$quarterly_achieved = [];

for ($m = 1; $m <= 12; $m++) {
    $month_key = sprintf("%02d", $m);
    $monthly_labels[] = $chart_months[$m-1];
    $monthly_targets[] = isset($monthly_data[$month_key]) ? round($monthly_data[$month_key]['target'], 2) : 0;
    $monthly_achieved[] = isset($monthly_data[$month_key]) ? round($monthly_data[$month_key]['achieved'], 2) : 0;
}

for ($q = 1; $q <= 4; $q++) {
    $quarterly_labels[] = $chart_quarters[$q-1];
    $quarterly_targets[] = isset($quarterly_data[$q]) ? round($quarterly_data[$q]['target'], 2) : 0;
    $quarterly_achieved[] = isset($quarterly_data[$q]) ? round($quarterly_data[$q]['achieved'], 2) : 0;
}

// إعداد بيانات المندوبين للرسم البياني
$sp_labels = [];
$sp_targets = [];
$sp_achieved = [];
$sp_percentages = [];

foreach ($salesperson_names as $id => $name) {
    $sp_labels[] = $name;
    $sp_targets[] = $salesperson_targets[$id];
    $sp_achieved[] = $salesperson_achieved[$id];
    $sp_percentages[] = $salesperson_targets[$id] > 0 ? round(($salesperson_achieved[$id] / $salesperson_targets[$id]) * 100, 1) : 0;
}

// تعيين عنوان الصفحة
$page_title = 'تقارير الأهداف';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير الأهداف</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير الأهداف</li>
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
                        <label for="year" class="form-label">السنة</label>
                        <select class="form-select" id="year" name="year">
                            <?php foreach ($available_years as $year) : ?>
                                <option value="<?php echo $year; ?>" <?php echo ($selected_year === $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    <div class="col-md-3 mb-3">
                        <label for="view" class="form-label">نوع العرض</label>
                        <select class="form-select" id="view" name="view">
                            <option value="summary" <?php echo ($view_type === 'summary') ? 'selected' : ''; ?>>ملخص</option>
                            <option value="detailed" <?php echo ($view_type === 'detailed') ? 'selected' : ''; ?>>تفصيلي</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end mb-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> عرض التقرير
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                    </div>
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
                        ملخص الأهداف لعام <?php echo $selected_year; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">إجمالي المستهدف</div>
                                            <div class="display-6"><?php echo formatMoney($total_target_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-bullseye fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">إجمالي المحقق</div>
                                            <div class="display-6"><?php echo formatMoney($total_achieved_amount); ?></div>
                                        </div>
                                        <div><i class="fas fa-check-circle fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">نسبة الإنجاز</div>
                                            <div class="display-6">
                                                <?php 
                                                    $overall_percentage = $total_target_amount > 0 ? round(($total_achieved_amount / $total_target_amount) * 100, 1) : 0;
                                                    echo $overall_percentage . '%';
                                                ?>
                                            </div>
                                        </div>
                                        <div><i class="fas fa-percent fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">عدد الأهداف</div>
                                            <div class="display-6"><?php echo $total_targets; ?></div>
                                        </div>
                                        <div><i class="fas fa-tasks fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- رسم بياني للأهداف الشهرية -->
                        <div class="col-md-12 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    الأهداف والإنجازات الشهرية
                                </div>
                                <div class="card-body">
                                    <canvas id="monthlyTargetsChart" width="100%" height="50"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- رسم بياني للأهداف الربعية -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-chart-bar me-1"></i>
                                    الأهداف الربعية
                                </div>
                                <div class="card-body">
                                    <canvas id="quarterlyTargetsChart" width="100%" height="50"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- رسم بياني لحالة الأهداف -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="fas fa-chart-pie me-1"></i>
                                    حالة الأهداف
                                </div>
                                <div class="card-body">
                                    <canvas id="targetsStatusChart" width="100%" height="50"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تقرير الأهداف حسب المندوب -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            الأهداف حسب المندوب
        </div>
        <div class="card-body">
            <?php if (!empty($salesperson_names)) : ?>
                <!-- رسم بياني للأهداف حسب المندوب -->
                <div class="mb-4">
                    <canvas id="salespersonTargetsChart" width="100%" height="50"></canvas>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>المستهدف</th>
                                <th>المحقق</th>
                                <th>نسبة الإنجاز</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $index = 1; ?>
                            <?php foreach ($salesperson_names as $id => $name) : ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo formatMoney($salesperson_targets[$id]); ?></td>
                                    <td><?php echo formatMoney($salesperson_achieved[$id]); ?></td>
                                    <td>
                                        <?php 
                                            $percentage = $salesperson_targets[$id] > 0 ? round(($salesperson_achieved[$id] / $salesperson_targets[$id]) * 100, 1) : 0;
                                            $color = getColorByPercentage($percentage);
                                        ?>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                                 style="width: <?php echo min($percentage, 100); ?>%;" 
                                                 aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="../targets/index.php?salesperson_id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i> عرض التفاصيل
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد بيانات أهداف متاحة للفترة المحددة.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($view_type === 'detailed' && !empty($targets)) : ?>
    <!-- تفاصيل الأهداف -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list me-1"></i>
            تفاصيل الأهداف
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered datatable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المندوب</th>
                            <th>الفترة</th>
                            <th>المستهدف</th>
                            <th>المحقق</th>
                            <th>نسبة الإنجاز</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($targets as $index => $t) : ?>
                            <?php
                                $percentage = $t['target_amount'] > 0 ? round(($t['achieved_amount'] / $t['target_amount']) * 100, 1) : 0;
                                $color = getColorByPercentage($percentage);
                                
                                // تحديد حالة الهدف
                                $status = '';
                                $status_class = '';
                                if ($today > $t['end_date']) {
                                    $status = 'منتهي';
                                    $status_class = 'secondary';
                                } elseif ($today >= $t['start_date'] && $today <= $t['end_date']) {
                                    $status = 'جاري';
                                    $status_class = 'info';
                                } else {
                                    $status = 'مستقبلي';
                                    $status_class = 'warning';
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($t['salesperson_name']); ?></td>
                                <td>
                                    <?php 
                                        echo date('Y-m-d', strtotime($t['start_date'])) . ' إلى ' . date('Y-m-d', strtotime($t['end_date']));
                                    ?>
                                </td>
                                <td><?php echo formatMoney($t['target_amount']); ?></td>
                                <td><?php echo formatMoney($t['achieved_amount']); ?></td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                             style="width: <?php echo min($percentage, 100); ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <a href="../targets/view.php?id=<?php echo $t['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../targets/edit.php?id=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// إعداد البيانات للرسوم البيانية
document.addEventListener('DOMContentLoaded', function() {
    // الرسم البياني الشهري
    const monthlyCtx = document.getElementById('monthlyTargetsChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'المستهدف',
                    data: <?php echo json_encode($monthly_targets); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'المحقق',
                    data: <?php echo json_encode($monthly_achieved); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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
            }
        }
    });
    
    // الرسم البياني الربعي
    const quarterlyCtx = document.getElementById('quarterlyTargetsChart').getContext('2d');
    const quarterlyChart = new Chart(quarterlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($quarterly_labels); ?>,
            datasets: [
                {
                    label: 'المستهدف',
                    data: <?php echo json_encode($quarterly_targets); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'المحقق',
                    data: <?php echo json_encode($quarterly_achieved); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
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
            }
        }
    });
    
    // الرسم البياني لحالة الأهداف
    const statusCtx = document.getElementById('targetsStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['منتهية', 'جارية', 'مستقبلية'],
            datasets: [{
                data: [
                    <?php echo $completed_targets; ?>, 
                    <?php echo $in_progress_targets; ?>, 
                    <?php echo $future_targets; ?>
                ],
                backgroundColor: [
                    'rgba(108, 117, 125, 0.7)',
                    'rgba(23, 162, 184, 0.7)',
                    'rgba(255, 193, 7, 0.7)'
                ],
                borderColor: [
                    'rgba(108, 117, 125, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(255, 193, 7, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    <?php if (!empty($salesperson_names)) : ?>
    // الرسم البياني للأهداف حسب المندوب
    const salespersonCtx = document.getElementById('salespersonTargetsChart').getContext('2d');
    const salespersonChart = new Chart(salespersonCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($sp_labels); ?>,
            datasets: [
                {
                    label: 'المستهدف',
                    data: <?php echo json_encode($sp_targets); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'المحقق',
                    data: <?php echo json_encode($sp_achieved); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    order: 3
                },
                {
                    label: 'نسبة الإنجاز %',
                    data: <?php echo json_encode($sp_percentages); ?>,
                    type: 'line',
                    fill: false,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    order: 1,
                    yAxisID: 'percentage'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'المبلغ'
                    }
                },
                percentage: {
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'النسبة المئوية'
                    }
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