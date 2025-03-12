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
require_once '../../classes/Visit.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$visit = new Visit($conn);
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
$status_filter = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : '';

// إعداد المرشحات للزيارات
$filters = [
    'start_date' => $start_date,
    'end_date' => $end_date
];

if ($salesperson_filter > 0) {
    $filters['salesperson_id'] = $salesperson_filter;
}

if (!empty($status_filter)) {
    $filters['visit_status'] = $status_filter;
}

// الحصول على بيانات الزيارات
$visits = $visit->readAll($filters);

// تحديد إحصائيات الزيارات
$total_visits = count($visits);
$completed_visits = 0;
$planned_visits = 0;
$cancelled_visits = 0;
$visits_by_company = [];
$visits_by_salesperson = [];

foreach ($visits as $v) {
    // حساب الزيارات حسب الحالة
    if ($v['visit_status'] === 'completed') {
        $completed_visits++;
    } elseif ($v['visit_status'] === 'planned') {
        $planned_visits++;
    } elseif ($v['visit_status'] === 'cancelled') {
        $cancelled_visits++;
    }
    
    // تجميع الزيارات حسب الشركة
    $company = $v['company_name'];
    if (!isset($visits_by_company[$company])) {
        $visits_by_company[$company] = [
            'total' => 0,
            'completed' => 0,
            'planned' => 0,
            'cancelled' => 0
        ];
    }
    $visits_by_company[$company]['total']++;
    if ($v['visit_status'] === 'completed') {
        $visits_by_company[$company]['completed']++;
    } elseif ($v['visit_status'] === 'planned') {
        $visits_by_company[$company]['planned']++;
    } elseif ($v['visit_status'] === 'cancelled') {
        $visits_by_company[$company]['cancelled']++;
    }
    
    // تجميع الزيارات حسب المندوب
    $salesperson = $v['salesperson_name'];
    if (!isset($visits_by_salesperson[$salesperson])) {
        $visits_by_salesperson[$salesperson] = [
            'total' => 0,
            'completed' => 0,
            'planned' => 0,
            'cancelled' => 0,
            'salesperson_id' => $v['salesperson_id']
        ];
    }
    $visits_by_salesperson[$salesperson]['total']++;
    if ($v['visit_status'] === 'completed') {
        $visits_by_salesperson[$salesperson]['completed']++;
    } elseif ($v['visit_status'] === 'planned') {
        $visits_by_salesperson[$salesperson]['planned']++;
    } elseif ($v['visit_status'] === 'cancelled') {
        $visits_by_salesperson[$salesperson]['cancelled']++;
    }
}

// ترتيب البيانات المجمعة
arsort($visits_by_company);
arsort($visits_by_salesperson);

// تعيين عنوان الصفحة
$page_title = 'تقارير الزيارات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير الزيارات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير الزيارات</li>
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
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">حالة الزيارة</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">جميع الحالات</option>
                            <option value="planned" <?php echo ($status_filter === 'planned') ? 'selected' : ''; ?>>مخططة</option>
                            <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-9 d-flex justify-content-end align-items-end mb-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> عرض التقرير
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                        <button type="button" class="btn btn-success ms-2" onclick="exportTableToExcel('visits-data', 'تقرير_الزيارات_<?php echo date('Y-m-d'); ?>')">
                            <i class="fas fa-file-excel me-1"></i> تصدير إلى Excel
                        </button>
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
                                            <div class="small">إجمالي الزيارات</div>
                                            <div class="display-6"><?php echo $total_visits; ?></div>
                                        </div>
                                        <div><i class="fas fa-calendar-check fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">الزيارات المكتملة</div>
                                            <div class="display-6"><?php echo $completed_visits; ?></div>
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
                                            <div class="small">الزيارات المخططة</div>
                                            <div class="display-6"><?php echo $planned_visits; ?></div>
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
                                            <div class="small">الزيارات الملغية</div>
                                            <div class="display-6"><?php echo $cancelled_visits; ?></div>
                                        </div>
                                        <div><i class="fas fa-times-circle fa-3x"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="visitsStatusChart" width="100%" height="50"></canvas>
                        </div>
                        <div class="col-md-6">
                            <canvas id="visitsDistributionChart" width="100%" height="50"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الإحصائيات حسب المندوب -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            إحصائيات الزيارات حسب المندوب
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المندوب</th>
                            <th>إجمالي الزيارات</th>
                            <th>المكتملة</th>
                            <th>المخططة</th>
                            <th>الملغية</th>
                            <th>نسبة الإكمال</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visits_by_salesperson)) : ?>
                            <?php $index = 1; ?>
                            <?php foreach ($visits_by_salesperson as $salesperson => $stats) : ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($salesperson); ?></td>
                                    <td><?php echo $stats['total']; ?></td>
                                    <td><?php echo $stats['completed']; ?></td>
                                    <td><?php echo $stats['planned']; ?></td>
                                    <td><?php echo $stats['cancelled']; ?></td>
                                    <td>
                                        <?php
                                            $completion_rate = ($stats['total'] > 0) ? ($stats['completed'] / $stats['total'] * 100) : 0;
                                            $color = getColorByPercentage($completion_rate);
                                            echo '<div class="progress">
                                                <div class="progress-bar bg-' . $color . '" role="progressbar" style="width: ' . $completion_rate . '%;" aria-valuenow="' . $completion_rate . '" aria-valuemin="0" aria-valuemax="100">
                                                    ' . number_format($completion_rate, 1) . '%
                                                </div>
                                            </div>';
                                        ?>
                                    </td>
                                    <td>
                                        <a href="visits.php?salesperson_id=<?php echo $stats['salesperson_id']; ?>&period=<?php echo $report_period; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i> تفاصيل
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
                </table>
            </div>
        </div>
    </div>
    
    <!-- الإحصائيات حسب الشركة -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-building me-1"></i>
            إحصائيات الزيارات حسب الشركة
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>الشركة</th>
                            <th>إجمالي الزيارات</th>
                            <th>المكتملة</th>
                            <th>المخططة</th>
                            <th>الملغية</th>
                            <th>نسبة الإكمال</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($visits_by_company)) : ?>
                            <?php $index = 1; ?>
                            <?php foreach ($visits_by_company as $company => $stats) : ?>
                                <?php if ($stats['total'] > 0) : ?>
                                <tr>
                                    <td><?php echo $index++; ?></td>
                                    <td><?php echo htmlspecialchars($company); ?></td>
                                    <td><?php echo $stats['total']; ?></td>
                                    <td><?php echo $stats['completed']; ?></td>
                                    <td><?php echo $stats['planned']; ?></td>
                                    <td><?php echo $stats['cancelled']; ?></td>
                                    <td>
                                        <?php
                                            $completion_rate = ($stats['total'] > 0) ? ($stats['completed'] / $stats['total'] * 100) : 0;
                                            $color = getColorByPercentage($completion_rate);
                                            echo '<div class="progress">
                                                <div class="progress-bar bg-' . $color . '" role="progressbar" style="width: ' . $completion_rate . '%;" aria-valuenow="' . $completion_rate . '" aria-valuemin="0" aria-valuemax="100">
                                                    ' . number_format($completion_rate, 1) . '%
                                                </div>
                                            </div>';
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">لا توجد بيانات متاحة للفترة المحددة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- قائمة تفصيلية بالزيارات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-list me-1"></i>
            قائمة تفصيلية بالزيارات
        </div>
        <div class="card-body">
            <?php if (count($visits) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="visits-data">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>الشركة</th>
                                <th>اسم العميل</th>
                                <th>تاريخ الزيارة</th>
                                <th>الغرض</th>
                                <th>النتيجة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits as $index => $v) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($v['salesperson_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['client_name']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($v['visit_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($v['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($v['outcome'] ?: 'غير محدد'); ?></td>
                                    <td>
                                        <?php 
                                            $status_badge = '';
                                            switch ($v['visit_status']) {
                                                case 'completed':
                                                    $status_badge = '<span class="badge bg-success">مكتملة</span>';
                                                    break;
                                                case 'planned':
                                                    $status_badge = '<span class="badge bg-warning">مخططة</span>';
                                                    break;
                                                case 'cancelled':
                                                    $status_badge = '<span class="badge bg-danger">ملغية</span>';
                                                    break;
                                                default:
                                                    $status_badge = '<span class="badge bg-secondary">' . $v['visit_status'] . '</span>';
                                            }
                                            echo $status_badge;
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد زيارات متاحة للفترة المحددة.
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
    // الرسم البياني للزيارات حسب الحالة
    var statusCtx = document.getElementById('visitsStatusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مكتملة', 'مخططة', 'ملغية'],
            datasets: [{
                data: [
                    <?php echo $completed_visits; ?>,
                    <?php echo $planned_visits; ?>,
                    <?php echo $cancelled_visits; ?>
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
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع حالة الزيارات'
                }
            }
        }
    });
    
    // الرسم البياني لتوزيع الزيارات بين المندوبين
    var distributionCtx = document.getElementById('visitsDistributionChart').getContext('2d');
    var distributionData = {
        labels: [
            <?php 
                $salesperson_names = [];
                $salesperson_counts = [];
                
                $i = 0;
                foreach ($visits_by_salesperson as $salesperson => $stats) {
                    if ($i < 5) { // أخذ أعلى 5 مندوبين فقط للعرض
                        $salesperson_names[] = "'" . $salesperson . "'";
                        $salesperson_counts[] = $stats['total'];
                        $i++;
                    }
                }
                
                echo implode(', ', $salesperson_names);
            ?>
        ],
        datasets: [{
            label: 'عدد الزيارات',
            data: [
                <?php echo implode(', ', $salesperson_counts); ?>
            ],
            backgroundColor: [
                'rgba(0, 123, 255, 0.7)',
                'rgba(40, 167, 69, 0.7)',
                'rgba(220, 53, 69, 0.7)',
                'rgba(255, 193, 7, 0.7)',
                'rgba(23, 162, 184, 0.7)'
            ],
            borderColor: [
                'rgba(0, 123, 255, 1)',
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(23, 162, 184, 1)'
            ],
            borderWidth: 1
        }]
    };
    
    var distributionChart = new Chart(distributionCtx, {
        type: 'bar',
        data: distributionData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع الزيارات بين المندوبين (أعلى 5)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});

// دالة تصدير البيانات إلى Excel
function exportTableToExcel(tableID, filename = '') {
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
    
    // تحديد اسم الملف
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    
    // إنشاء رابط التنزيل
    downloadLink = document.createElement("a");
    
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob([tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob(blob, filename);
    } else {
        // إنشاء رابط تنزيل للمتصفحات الأخرى
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}