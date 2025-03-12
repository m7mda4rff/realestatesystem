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
require_once '../../classes/User.php';
require_once '../../classes/Sale.php';
require_once '../../classes/Target.php';
require_once '../../classes/Visit.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$user = new User($conn);
$sale = new Sale($conn);
$target = new Target($conn);
$visit = new Visit($conn);

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

// إعداد مصفوفة لتخزين بيانات الأداء
$performance_data = [];

// الحصول على بيانات الأداء لكل مندوب
foreach ($salespersons as $person) {
    $person_id = $person['id'];
    
    // إذا تم تحديد مندوب معين، نتجاوز بقية المندوبين
    if ($salesperson_filter > 0 && $person_id != $salesperson_filter) {
        continue;
    }
    
    // الحصول على إحصائيات المبيعات
    $sales_filters = [
        'salesperson_id' => $person_id,
        'start_date' => $start_date,
        'end_date' => $end_date
    ];
    $sales_stats = $sale->getSalesStats($report_period);
    $sales_list = $sale->readAll($sales_filters);
    
    // الحصول على هدف المندوب الحالي
    $current_target = $target->getCurrentTarget($person_id);
    
    // الحصول على إحصائيات الزيارات
    $visit_stats = $visit->getVisitStats($person_id, $report_period);
    
    // حساب نسب الأداء
    $target_achievement = ($current_target['target_amount'] > 0) ? 
        ($current_target['achieved_amount'] / $current_target['target_amount'] * 100) : 0;
    
    $visits_completion_rate = ($visit_stats['total'] > 0) ? 
        ($visit_stats['completed'] / $visit_stats['total'] * 100) : 0;
    
    $sales_conversion_rate = ($visit_stats['completed'] > 0) ? 
        (count($sales_list) / $visit_stats['completed'] * 100) : 0;
    
    // تخزين البيانات
    $performance_data[$person_id] = [
        'id' => $person_id,
        'name' => $person['full_name'],
        'email' => $person['email'],
        'phone' => $person['phone'],
        // المبيعات
        'sales_count' => count($sales_list),
        'sales_amount' => array_sum(array_column($sales_list, 'amount')),
        'commissions' => array_sum(array_column($sales_list, 'commission_amount')),
        // الأهداف
        'target_amount' => $current_target['target_amount'],
        'achieved_amount' => $current_target['achieved_amount'],
        'target_achievement' => round($target_achievement, 1),
        // الزيارات
        'visits_total' => $visit_stats['total'],
        'visits_completed' => $visit_stats['completed'],
        'visits_cancelled' => $visit_stats['cancelled'],
        'visits_completion_rate' => round($visits_completion_rate, 1),
        // معدلات الأداء
        'sales_conversion_rate' => round($sales_conversion_rate, 1),
        'avg_sale_amount' => (count($sales_list) > 0) ? 
            (array_sum(array_column($sales_list, 'amount')) / count($sales_list)) : 0
    ];
}

// ترتيب بيانات الأداء حسب المبيعات
usort($performance_data, function($a, $b) {
    return $b['sales_amount'] - $a['sales_amount'];
});

// تعيين عنوان الصفحة
$page_title = 'تقارير الأداء';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقارير الأداء</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">تقارير الأداء</li>
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
                    <?php if (count($performance_data) > 0) : ?>
                        <!-- مؤشرات الأداء الرئيسية -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">إجمالي المبيعات</div>
                                                <div class="display-6"><?php 
                                                    $total_sales = array_sum(array_column($performance_data, 'sales_amount'));
                                                    echo formatMoney($total_sales); 
                                                ?></div>
                                            </div>
                                            <div><i class="fas fa-shopping-cart fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">إجمالي العمولات</div>
                                                <div class="display-6"><?php 
                                                    $total_commissions = array_sum(array_column($performance_data, 'commissions'));
                                                    echo formatMoney($total_commissions); 
                                                ?></div>
                                            </div>
                                            <div><i class="fas fa-coins fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">إجمالي الزيارات</div>
                                                <div class="display-6"><?php 
                                                    $total_visits = array_sum(array_column($performance_data, 'visits_total'));
                                                    echo $total_visits; 
                                                ?></div>
                                            </div>
                                            <div><i class="fas fa-calendar-check fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">معدل تحويل الزيارات</div>
                                                <div class="display-6"><?php 
                                                    $total_completed_visits = array_sum(array_column($performance_data, 'visits_completed'));
                                                    $total_sales_count = array_sum(array_column($performance_data, 'sales_count'));
                                                    $conversion_rate = ($total_completed_visits > 0) ? 
                                                        ($total_sales_count / $total_completed_visits * 100) : 0;
                                                    echo round($conversion_rate, 1) . '%'; 
                                                ?></div>
                                            </div>
                                            <div><i class="fas fa-exchange-alt fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- الرسوم البيانية للأداء -->
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="targetAchievementChart" width="100%" height="50"></canvas>
                            </div>
                            <div class="col-md-6">
                                <canvas id="visitsConversionChart" width="100%" height="50"></canvas>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> لا توجد بيانات متاحة للفترة المحددة
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تفاصيل أداء المندوبين -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-tie me-1"></i>
            تفاصيل أداء المندوبين
        </div>
        <div class="card-body">
            <?php if (count($performance_data) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>المبيعات</th>
                                <th>تحقيق الهدف</th>
                                <th>الزيارات</th>
                                <th>معدل التحويل</th>
                                <th>متوسط المبيعة</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_data as $index => $person_data) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($person_data['name']); ?></td>
                                    <td>
                                        <div><?php echo formatMoney($person_data['sales_amount']); ?></div>
                                        <small class="text-muted"><?php echo $person_data['sales_count']; ?> مبيعة</small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getColorByPercentage($person_data['target_achievement']); ?>" 
                                                role="progressbar" 
                                                style="width: <?php echo min($person_data['target_achievement'], 100); ?>%;" 
                                                aria-valuenow="<?php echo $person_data['target_achievement']; ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                                <?php echo $person_data['target_achievement']; ?>%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatMoney($person_data['achieved_amount']); ?> من 
                                            <?php echo formatMoney($person_data['target_amount']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div><?php echo $person_data['visits_total']; ?> زيارة</div>
                                        <small class="text-muted">
                                            <?php echo $person_data['visits_completed']; ?> مكتملة، 
                                            <?php echo $person_data['visits_cancelled']; ?> ملغية
                                        </small>
                                    </td>
                                    <td>
                                        <div><?php echo $person_data['sales_conversion_rate']; ?>%</div>
                                        <small class="text-muted">
                                            <?php echo $person_data['sales_count']; ?> مبيعة من 
                                            <?php echo $person_data['visits_completed']; ?> زيارة
                                        </small>
                                    </td>
                                    <td><?php echo formatMoney($person_data['avg_sale_amount']); ?></td>
                                    <td>
                                        <a href="../salespeople/view.php?id=<?php echo $person_data['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="sales.php?salesperson_id=<?php echo $person_data['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-chart-bar"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد بيانات متاحة للفترة المحددة
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- مقارنة المندوبين -->
    <?php if (count($performance_data) > 1) : ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-bar me-1"></i>
            مقارنة أداء المندوبين
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <canvas id="salesComparisonChart" width="100%" height="50"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="visitsComparisonChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
    <?php if (count($performance_data) > 0) : ?>
    // الرسم البياني لتحقيق الأهداف
    const targetCtx = document.getElementById('targetAchievementChart').getContext('2d');
    const targetChart = new Chart(targetCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                    $names = [];
                    $achievements = [];
                    
                    foreach ($performance_data as $person_data) {
                        $names[] = "'" . $person_data['name'] . "'";
                        $achievements[] = $person_data['target_achievement'];
                    }
                    
                    echo implode(', ', $names);
                ?>
            ],
            datasets: [{
                label: 'نسبة تحقيق الهدف (%)',
                data: [
                    <?php echo implode(', ', $achievements); ?>
                ],
                backgroundColor: [
                    <?php 
                        $colors = [];
                        foreach ($achievements as $achievement) {
                            switch (getColorByPercentage($achievement)) {
                                case 'success':
                                    $colors[] = "'rgba(40, 167, 69, 0.7)'";
                                    break;
                                case 'info':
                                    $colors[] = "'rgba(23, 162, 184, 0.7)'";
                                    break;
                                case 'primary':
                                    $colors[] = "'rgba(0, 123, 255, 0.7)'";
                                    break;
                                case 'warning':
                                    $colors[] = "'rgba(255, 193, 7, 0.7)'";
                                    break;
                                case 'danger':
                                    $colors[] = "'rgba(220, 53, 69, 0.7)'";
                                    break;
                                default:
                                    $colors[] = "'rgba(0, 123, 255, 0.7)'";
                            }
                        }
                        echo implode(', ', $colors);
                    ?>
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
                    text: 'نسبة تحقيق الأهداف'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // الرسم البياني لمعدل تحويل الزيارات
    const conversionCtx = document.getElementById('visitsConversionChart').getContext('2d');
    const conversionChart = new Chart(conversionCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php echo implode(', ', $names); ?>
            ],
            datasets: [{
                label: 'معدل تحويل الزيارات (%)',
                data: [
                    <?php 
                        $conversions = [];
                        foreach ($performance_data as $person_data) {
                            $conversions[] = $person_data['sales_conversion_rate'];
                        }
                        echo implode(', ', $conversions);
                    ?>
                ],
                backgroundColor: 'rgba(255, 193, 7, 0.7)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'معدل تحويل الزيارات إلى مبيعات'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    <?php if (count($performance_data) > 1) : ?>
    // الرسم البياني لمقارنة المبيعات
    const salesComparisonCtx = document.getElementById('salesComparisonChart').getContext('2d');
    const salesComparisonChart = new Chart(salesComparisonCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php echo implode(', ', $names); ?>
            ],
            datasets: [{
                label: 'المبيعات',
                data: [
                    <?php 
                        $sales_amounts = [];
                        foreach ($performance_data as $person_data) {
                            $sales_amounts[] = $person_data['sales_amount'];
                        }
                        echo implode(', ', $sales_amounts);
                    ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'مقارنة المبيعات بين المندوبين'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // الرسم البياني لمقارنة الزيارات
    const visitsComparisonCtx = document.getElementById('visitsComparisonChart').getContext('2d');
    const visitsComparisonChart = new Chart(visitsComparisonCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php echo implode(', ', $names); ?>
            ],
            datasets: [
                {
                    label: 'زيارات مكتملة',
                    data: [
                        <?php 
                            $completed_visits = [];
                            foreach ($performance_data as $person_data) {
                                $completed_visits[] = $person_data['visits_completed'];
                            }
                            echo implode(', ', $completed_visits);
                        ?>
                    ],
                    backgroundColor: 'rgba(40, 167, 69, 0.7)'
                },
                {
                    label: 'زيارات ملغية',
                    data: [
                        <?php 
                            $cancelled_visits = [];
                            foreach ($performance_data as $person_data) {
                                $cancelled_visits[] = $person_data['visits_cancelled'];
                            }
                            echo implode(', ', $cancelled_visits);
                        ?>
                    ],
                    backgroundColor: 'rgba(220, 53, 69, 0.7)'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'مقارنة الزيارات بين المندوبين'
                }
            },
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>