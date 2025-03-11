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
require_once '../classes/Target.php';
require_once '../classes/Sale.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$sale = new Sale($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// الحصول على الهدف الحالي
$current_target = $target->getCurrentTarget($user_id);
$target_amount = $current_target['target_amount'];
$achieved_amount = $current_target['achieved_amount'];
$start_date = isset($current_target['start_date']) ? $current_target['start_date'] : date('Y-m-01');
$end_date = isset($current_target['end_date']) ? $current_target['end_date'] : date('Y-m-t');

// حساب نسبة تحقيق الهدف
$target_percentage = calculateAchievement($achieved_amount, $target_amount);

// الحصول على معلومات المبيعات
$sales_data = [];
$date_labels = [];

// تحديد فترة الهدف بالأيام
$current_date = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$interval = new DateInterval('P1D'); // فترة يوم واحد
$date_range = new DatePeriod($current_date, $interval, $end_date_obj->modify('+1 day'));

// بناء مصفوفة التواريخ
foreach ($date_range as $date) {
    $date_key = $date->format('Y-m-d');
    $date_labels[] = $date->format('d/m');
    $sales_data[$date_key] = 0;
}

// الحصول على المبيعات خلال فترة الهدف
$sales = $sale->readAll([
    'salesperson_id' => $user_id,
    'start_date' => $start_date,
    'end_date' => $end_date
]);

// تجميع المبيعات حسب التاريخ
foreach ($sales as $sale_item) {
    $sale_date = date('Y-m-d', strtotime($sale_item['sale_date']));
    if (isset($sales_data[$sale_date])) {
        $sales_data[$sale_date] += $sale_item['amount'];
    }
}

// تحويل المبيعات إلى مصفوفة تراكمية للرسم البياني
$cumulative_sales = [];
$running_total = 0;
foreach ($sales_data as $date => $amount) {
    $running_total += $amount;
    $cumulative_sales[] = $running_total;
}

// الحصول على أهداف المندوب السابقة
$previous_targets = $target->getTargetsBySalesperson($user_id, 5);

// تعيين عنوان الصفحة
$page_title = 'أهدافي';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">أهدافي</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
        <li class="breadcrumb-item active">أهدافي</li>
    </ol>
    
    <!-- بطاقة الهدف الحالي -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-bullseye me-1"></i>
                        الهدف الحالي
                    </div>
                    <div>
                        <small class="text-muted">
                            الفترة: <?php echo date('Y-m-d', strtotime($start_date)); ?> إلى <?php echo date('Y-m-d', strtotime($end_date)); ?>
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">المبلغ المستهدف</h5>
                                    <h2 class="mb-0"><?php echo formatMoney($target_amount); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">المبلغ المحقق</h5>
                                    <h2 class="mb-0"><?php echo formatMoney($achieved_amount); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">نسبة التحقيق</h5>
                                    <h2 class="mb-0"><?php echo $target_percentage; ?>%</h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">التقدم نحو الهدف</h5>
                    <div class="progress mb-4" style="height: 25px;">
                        <?php $color = getColorByPercentage($target_percentage); ?>
                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo min($target_percentage, 100); ?>%;" aria-valuenow="<?php echo $target_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $target_percentage; ?>%
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">تقدم المبيعات اليومي</h5>
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="dailySalesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة الأهداف السابقة -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-history me-1"></i>
            الأهداف السابقة
        </div>
        <div class="card-body">
            <?php if (count($previous_targets) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>الفترة</th>
                                <th>المبلغ المستهدف</th>
                                <th>المبلغ المحقق</th>
                                <th>نسبة التحقيق</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previous_targets as $index => $t) : ?>
                                <?php 
                                    // تخطي الهدف الحالي إذا كان موجودًا في القائمة
                                    if (isset($current_target['id']) && $t['id'] == $current_target['id']) {
                                        continue;
                                    }
                                    
                                    $t_percentage = calculateAchievement($t['achieved_amount'], $t['target_amount']);
                                    $t_color = getColorByPercentage($t_percentage);
                                    $t_status = $t_percentage >= 100 ? 'مكتمل' : 'غير مكتمل';
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($t['start_date'])); ?> - 
                                        <?php echo date('Y-m-d', strtotime($t['end_date'])); ?>
                                    </td>
                                    <td><?php echo formatMoney($t['target_amount']); ?></td>
                                    <td><?php echo formatMoney($t['achieved_amount']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $t_color; ?>" role="progressbar" style="width: <?php echo min($t_percentage, 100); ?>%;" aria-valuenow="<?php echo $t_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $t_percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $t_percentage >= 100 ? 'success' : 'warning'; ?>">
                                            <?php echo $t_status; ?>
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
    
    <!-- المعلومات الإرشادية والنصائح -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-lightbulb me-1"></i>
            نصائح لتحقيق الهدف
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>كيفية زيادة المبيعات</h5>
                    <ul>
                        <li>زيادة عدد الزيارات الميدانية للعملاء المحتملين.</li>
                        <li>متابعة العملاء الحاليين بشكل دوري.</li>
                        <li>التركيز على العملاء ذوي الفرص الأعلى للشراء.</li>
                        <li>تحسين تقنيات العرض والإقناع.</li>
                        <li>الاطلاع على أحدث العروض والمنتجات المتاحة.</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>المكافآت المتاحة</h5>
                    <p>عند تحقيق الهدف الشهري، قد تكون مؤهلاً للحصول على:</p>
                    <ul>
                        <li>مكافأة مالية إضافية.</li>
                        <li>زيادة نسبة العمولة في الشهر التالي.</li>
                        <li>فرص تدريبية متقدمة.</li>
                        <li>ترشيح للحصول على جائزة أفضل مندوب مبيعات.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني للمبيعات اليومية التراكمية
    var dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
    var dailySalesChart = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($date_labels); ?>,
            datasets: [
                {
                    label: 'المبيعات التراكمية',
                    data: <?php echo json_encode($cumulative_sales); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'الهدف',
                    data: Array(<?php echo count($date_labels); ?>).fill(<?php echo $target_amount; ?>),
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    borderDash: [5, 5]
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'تقدم المبيعات اليومي مقارنة بالهدف'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'المبلغ (ج.م)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'التاريخ'
                    }
                }
            }
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>