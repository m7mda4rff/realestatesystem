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

// الحصول على إحصائيات المبيعات للشهر الحالي
$current_month = date('Y-m');
$current_month_sales = $sale->getMonthlySales($user_id, $current_month);
$sales_count = $current_month_sales['count'];
$sales_amount = $current_month_sales['amount'];
$commission_amount = $current_month_sales['commission'];

// الحصول على الهدف الحالي
$current_target = $target->getCurrentTarget($user_id);
$target_amount = $current_target['target_amount'];
$achieved_amount = $current_target['achieved_amount'];

// حساب نسبة تحقيق الهدف
$target_percentage = calculateAchievement($achieved_amount, $target_amount);

// الحصول على إحصائيات العمولات
$commission_stats = $commission->getCommissionStats($user_id, 'month');
$pending_commission = $commission_stats['pending_amount'];
$paid_commission = $commission_stats['paid_amount'];

// الحصول على إحصائيات الزيارات
$visit_stats = $visit->getVisitStats($user_id, 'month');
$total_visits = $visit_stats['total'];
$planned_visits = $visit_stats['planned'];
$completed_visits = $visit_stats['completed'];

// الحصول على الزيارات القادمة
$upcoming_visits = $visit->getUpcomingVisits($user_id, 5);

// الحصول على آخر المبيعات
$recent_sales = $sale->getRecentSales($user_id, 5);

// في حالة وجود مشكلة في إظهار البيانات، قم بتفعيل هذا الكود للتشخيص
/*
// جلب جميع مبيعات المندوب
$all_sales = $sale->readAll(['salesperson_id' => $user_id]);

// حساب المبالغ يدوياً
if ($sales_amount == 0 && !empty($all_sales)) {
    $manual_sales_amount = 0;
    $manual_commission_amount = 0;
    $manual_sales_count = 0;
    
    foreach ($all_sales as $sale_item) {
        // التحقق من تاريخ المبيعة في الشهر الحالي
        $sale_month = date('Y-m', strtotime($sale_item['sale_date']));
        if ($sale_month == $current_month) {
            $manual_sales_amount += $sale_item['amount'];
            $manual_commission_amount += $sale_item['commission_amount'];
            $manual_sales_count++;
        }
    }
    
    // استبدال القيم
    $sales_amount = $manual_sales_amount;
    $commission_amount = $manual_commission_amount;
    $sales_count = $manual_sales_count;
}
*/

// تعيين عنوان الصفحة
$page_title = 'لوحة تحكم مندوب المبيعات';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">لوحة التحكم</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">لوحة التحكم</li>
    </ol>
    
    <!-- بطاقات الملخص -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($sales_amount); ?></h4>
                            <div class="small">إجمالي المبيعات الشهرية</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-chart-line fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mysales.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($commission_amount); ?></h4>
                            <div class="small">العمولات المستحقة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-coins fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mycommissions.php">عرض التفاصيل</a>
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
                            <div class="small">من الهدف الشهري</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-bullseye fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="mytargets.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo count($upcoming_visits); ?></h4>
                            <div class="small">الزيارات القادمة</div>
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

    <!-- شريط التقدم للهدف -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bullseye me-1"></i>
                    تقدم تحقيق الهدف الشهري
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo formatMoney($achieved_amount); ?> من <?php echo formatMoney($target_amount); ?></h5>
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

    <!-- الزيارات القادمة والمبيعات الأخيرة -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calendar-check me-1"></i>
                    الزيارات القادمة
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_visits) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>الشركة</th>
                                        <th>العميل</th>
                                        <th>التاريخ</th>
                                        <th>الوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_visits as $visit) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($visit['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($visit['client_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($visit['visit_time'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($visit['visit_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-center">لا توجد زيارات قادمة مجدولة.</p>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="visits/add.php" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة زيارة جديدة</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    آخر المبيعات
                </div>
                <div class="card-body">
                    <?php if (count($recent_sales) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>العميل</th>
                                        <th>التاريخ</th>
                                        <th>المبلغ</th>
                                        <th>العمولة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sales as $sale) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale['client_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($sale['sale_date'])); ?></td>
                                            <td><?php echo formatMoney($sale['amount']); ?></td>
                                            <td><?php echo formatMoney($sale['commission_amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-center">لا توجد مبيعات حديثة.</p>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="mysales.php" class="btn btn-primary">عرض جميع المبيعات</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- إحصائيات شهرية -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    إحصائيات المبيعات الشهرية
                </div>
                <div class="card-body">
                    <canvas id="salesChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    إحصائيات الزيارات الشهرية
                </div>
                <div class="card-body">
                    <canvas id="visitsChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- إضافة JavaScript خاص بالصفحة -->
<?php
$page_specific_js = '
<script>
// إعداد بيانات الرسم البياني للمبيعات
var salesCtx = document.getElementById("salesChart").getContext("2d");
var salesChart = new Chart(salesCtx, {
    type: "bar",
    data: {
        labels: ["المبيعات", "العمولات"],
        datasets: [{
            label: "المبلغ (ج.م)",
            backgroundColor: ["rgba(0, 97, 242, 0.7)", "rgba(40, 167, 69, 0.7)"],
            borderColor: ["rgba(0, 97, 242, 1)", "rgba(40, 167, 69, 1)"],
            borderWidth: 1,
            data: [' . $sales_amount . ', ' . $commission_amount . ']
        }]
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

// إعداد بيانات الرسم البياني للزيارات
var visitsCtx = document.getElementById("visitsChart").getContext("2d");
var visitsChart = new Chart(visitsCtx, {
    type: "pie",
    data: {
        labels: ["مخططة", "مكتملة", "ملغية"],
        datasets: [{
            data: [' . $visit_stats['planned'] . ', ' . $visit_stats['completed'] . ', ' . $visit_stats['cancelled'] . '],
            backgroundColor: ["rgba(0, 123, 255, 0.7)", "rgba(40, 167, 69, 0.7)", "rgba(220, 53, 69, 0.7)"],
            borderColor: ["rgba(0, 123, 255, 1)", "rgba(40, 167, 69, 1)", "rgba(220, 53, 69, 1)"],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>';
?>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>