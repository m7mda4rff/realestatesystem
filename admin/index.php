<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Sale.php';
require_once '../classes/User.php';
require_once '../classes/Client.php';
require_once '../classes/Visit.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);
$client = new Client($conn);
$visit = new Visit($conn);

// الحصول على إحصائيات المبيعات
$sales_stats = $sale->getSalesStats('month');
$sales_total = $sales_stats['amount'];
$sales_count = $sales_stats['count'];

// الحصول على إحصائيات المستخدمين
$all_users = $user->readAll();
$users_count = count($all_users);
$salespeople = $user->readAll(['role' => 'salesperson']);
$salespeople_count = count($salespeople);
$managers = $user->readAll(['role' => 'manager']);
$managers_count = count($managers);

// الحصول على إحصائيات العملاء
$all_clients = $client->readAll();
$clients_count = count($all_clients);

// الحصول على إحصائيات الزيارات
$user_id = $_SESSION['user_id']; // استخدم معرف المستخدم للحصول على إحصائيات الزيارات
$visits_stats = $visit->getVisitStats($user_id, 'month');
$visits_count = $visits_stats['total'];
$completed_visits = $visits_stats['completed'];

// الحصول على أحدث 5 مبيعات
$recent_sales = $sale->readAll(['limit' => 5]);

// الحصول على أحدث 5 مستخدمين
$recent_users = array_slice($all_users, 0, 5);

// الحصول على إحصائيات المبيعات حسب المندوب
$sales_by_salesperson = $sale->getSalesBySalespersonStats('month');

// تعيين عنوان الصفحة
$page_title = 'لوحة تحكم المسؤول';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">لوحة تحكم المسؤول</h1>
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
                            <h4 class="mb-0"><?php echo formatMoney($sales_total); ?></h4>
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
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $clients_count; ?></h4>
                            <div class="small">عدد العملاء</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="clients/index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $salespeople_count; ?></h4>
                            <div class="small">مندوبي المبيعات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-user-tie fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="users/index.php?role=salesperson">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $visits_count; ?></h4>
                            <div class="small">زيارات هذا الشهر</div>
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
    
    <!-- الرسوم البيانية -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    المبيعات الشهرية
                </div>
                <div class="card-body">
                    <canvas id="monthlySalesChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    توزيع المبيعات حسب المندوب
                </div>
                <div class="card-body">
                    <canvas id="salesByAgentChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- أحدث المبيعات والمستخدمين -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    أحدث المبيعات
                </div>
                <div class="card-body">
                    <?php if (count($recent_sales) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>العميل</th>
                                        <th>المندوب</th>
                                        <th>التاريخ</th>
                                        <th>المبلغ</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sales as $sale_item) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
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
                        <p class="text-center">لا توجد مبيعات حديثة.</p>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="sales/index.php" class="btn btn-primary">عرض جميع المبيعات</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-users me-1"></i>
                    أحدث المستخدمين
                </div>
                <div class="card-body">
                    <?php if (count($recent_users) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>الاسم</th>
                                        <th>اسم المستخدم</th>
                                        <th>الدور</th>
                                        <th>تاريخ الإنشاء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user_item) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user_item['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                                            <td>
                                                <?php
                                                    switch ($user_item['role']) {
                                                        case 'admin':
                                                            echo 'مسؤول النظام';
                                                            break;
                                                        case 'manager':
                                                            echo 'مدير مبيعات';
                                                            break;
                                                        case 'salesperson':
                                                            echo 'مندوب مبيعات';
                                                            break;
                                                        default:
                                                            echo $user_item['role'];
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user_item['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p class="text-center">لا يوجد مستخدمين حديثين.</p>
                    <?php endif; ?>
                    <div class="text-center mt-3">
                        <a href="users/index.php" class="btn btn-primary">عرض جميع المستخدمين</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- إضافة JavaScript خاص بالصفحة -->
<?php
// استخراج بيانات الرسوم البيانية
$chart_data = $sale->getChartData('month');
$months = [];
$amounts = [];

foreach ($chart_data as $data) {
    $months[] = date('d/m', strtotime($data['date_label']));
    $amounts[] = $data['total_amount'];
}

// تحضير بيانات رسم المبيعات حسب المندوب
$agent_names = [];
$agent_sales = [];

foreach ($sales_by_salesperson as $agent_data) {
    $agent_names[] = $agent_data['salesperson_name'];
    $agent_sales[] = $agent_data['amount'];
}

$page_specific_js = '
<script>
// إعداد بيانات الرسم البياني للمبيعات الشهرية
var salesCtx = document.getElementById("monthlySalesChart").getContext("2d");
var monthlySalesChart = new Chart(salesCtx, {
    type: "bar",
    data: {
        labels: ' . json_encode($months) . ',
        datasets: [{
            label: "المبيعات (ج.م)",
            backgroundColor: "rgba(0, 97, 242, 0.7)",
            borderColor: "rgba(0, 97, 242, 1)",
            borderWidth: 1,
            data: ' . json_encode($amounts) . '
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

// إعداد بيانات الرسم البياني للمبيعات حسب المندوب
var agentCtx = document.getElementById("salesByAgentChart").getContext("2d");
var salesByAgentChart = new Chart(agentCtx, {
    type: "pie",
    data: {
        labels: ' . json_encode($agent_names) . ',
        datasets: [{
            data: ' . json_encode($agent_sales) . ',
            backgroundColor: [
                "rgba(0, 97, 242, 0.7)",
                "rgba(40, 167, 69, 0.7)",
                "rgba(220, 53, 69, 0.7)",
                "rgba(255, 193, 7, 0.7)",
                "rgba(23, 162, 184, 0.7)",
                "rgba(111, 66, 193, 0.7)"
            ],
            borderColor: [
                "rgba(0, 97, 242, 1)",
                "rgba(40, 167, 69, 1)",
                "rgba(220, 53, 69, 1)",
                "rgba(255, 193, 7, 1)",
                "rgba(23, 162, 184, 1)",
                "rgba(111, 66, 193, 1)"
            ],
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