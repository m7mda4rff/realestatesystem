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
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$user = new User($conn);
$sale = new Sale($conn);

// التحقق من معرف الهدف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$target_id = (int)$_GET['id'];

// الحصول على معلومات الهدف
if (!$target->readOne($target_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على الهدف المطلوب';
    header('Location: index.php');
    exit;
}

// الحصول على معلومات المندوب
$salesperson = new User($conn);
$salesperson->readOne($target->salesperson_id);

// الحصول على المبيعات ضمن فترة الهدف
$sales_query = "SELECT s.*, c.name as client_name
               FROM sales s
               LEFT JOIN clients c ON s.client_id = c.id
               WHERE s.salesperson_id = ? 
               AND DATE(s.sale_date) BETWEEN ? AND ?
               ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("iss", $target->salesperson_id, $target->start_date, $target->end_date);
$stmt->execute();
$sales_result = $stmt->get_result();
$sales = [];
while ($row = $sales_result->fetch_assoc()) {
    $sales[] = $row;
}

// حساب إحصائيات المبيعات
$total_sales = count($sales);
$total_amount = 0;
$total_commission = 0;
$sales_by_status = [
    'paid' => 0,
    'pending' => 0,
    'cancelled' => 0
];

foreach ($sales as $sale_item) {
    $total_amount += $sale_item['amount'];
    $total_commission += $sale_item['commission_amount'];
    $sales_by_status[$sale_item['payment_status']]++;
}

// حساب نسبة تحقيق الهدف
$achievement_percentage = calculateAchievement($target->achieved_amount, $target->target_amount);
$progress_color = getColorByPercentage($achievement_percentage);

// تحديد حالة الهدف (حالي، سابق، مستقبلي)
$today = date('Y-m-d');
$target_status = '';
$target_status_label = '';

if ($today >= $target->start_date && $today <= $target->end_date) {
    $target_status = 'current';
    $target_status_label = '<span class="badge bg-info">حالي</span>';
} elseif ($today > $target->end_date) {
    $target_status = 'past';
    $target_status_label = '<span class="badge bg-secondary">منتهي</span>';
} else {
    $target_status = 'future';
    $target_status_label = '<span class="badge bg-warning">مستقبلي</span>';
}

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل الهدف';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل الهدف</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة الأهداف</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل الهدف</li>
    </ol>
    
    <!-- أزرار الإجراءات -->
    <div class="mb-4">
        <a href="edit.php?id=<?php echo $target_id; ?>" class="btn btn-primary me-2">
            <i class="fas fa-edit me-1"></i> تعديل الهدف
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
        </a>
        <a href="index.php?action=delete&id=<?php echo $target_id; ?>" class="btn btn-danger float-start ms-2" onclick="return confirm('هل أنت متأكد من حذف هذا الهدف؟');">
            <i class="fas fa-trash me-1"></i> حذف الهدف
        </a>
    </div>
    
    <div class="row">
        <!-- بطاقة معلومات الهدف -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bullseye me-1"></i>
                    معلومات الهدف <?php echo $target_status_label; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h5>تقدم تحقيق الهدف</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo min($achievement_percentage, 100); ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo $achievement_percentage; ?>%
                            </div>
                        </div>
                        <p class="text-center">
                            <strong><?php echo formatMoney($target->achieved_amount); ?></strong> من أصل <strong><?php echo formatMoney($target->target_amount); ?></strong>
                        </p>
                    </div>
                    
                    <table class="table table-bordered">
                        <tr>
                            <th class="bg-light" width="35%">المندوب</th>
                            <td><?php echo htmlspecialchars($salesperson->full_name); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">مبلغ الهدف</th>
                            <td><?php echo formatMoney($target->target_amount); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">المبلغ المحقق</th>
                            <td><?php echo formatMoney($target->achieved_amount); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">نسبة التحقيق</th>
                            <td><span class="badge bg-<?php echo $progress_color; ?>"><?php echo $achievement_percentage; ?>%</span></td>
                        </tr>
                        <tr>
                            <th class="bg-light">تاريخ البداية</th>
                            <td><?php echo date('Y-m-d', strtotime($target->start_date)); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">تاريخ النهاية</th>
                            <td><?php echo date('Y-m-d', strtotime($target->end_date)); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">منشئ الهدف</th>
                            <td><?php echo htmlspecialchars($target->created_by_name); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">تاريخ الإنشاء</th>
                            <td><?php echo date('Y-m-d H:i', strtotime($target->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">ملاحظات</th>
                            <td><?php echo !empty($target->notes) ? nl2br(htmlspecialchars($target->notes)) : 'لا توجد ملاحظات'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- بطاقة إحصائيات المبيعات -->
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    إحصائيات المبيعات للفترة
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white mb-4">
                                <div class="card-body text-center">
                                    <h5 class="mb-0"><?php echo $total_sales; ?></h5>
                                    <div class="small">عدد المبيعات</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white mb-4">
                                <div class="card-body text-center">
                                    <h5 class="mb-0"><?php echo formatMoney($total_amount); ?></h5>
                                    <div class="small">إجمالي المبيعات</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white mb-4">
                                <div class="card-body text-center">
                                    <h5 class="mb-0"><?php echo formatMoney($total_commission); ?></h5>
                                    <div class="small">العمولات</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>المبيعات حسب الحالة</h5>
                        <canvas id="salesStatusChart" width="100%" height="40"></canvas>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
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
                                    <td><?php echo $total_sales > 0 ? round(($sales_by_status['paid'] / $total_sales) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge status-pending">قيد الانتظار</span></td>
                                    <td><?php echo $sales_by_status['pending']; ?></td>
                                    <td><?php echo $total_sales > 0 ? round(($sales_by_status['pending'] / $total_sales) * 100, 1) : 0; ?>%</td>
                                </tr>
                                <tr>
                                    <td><span class="badge status-cancelled">ملغية</span></td>
                                    <td><?php echo $sales_by_status['cancelled']; ?></td>
                                    <td><?php echo $total_sales > 0 ? round(($sales_by_status['cancelled'] / $total_sales) * 100, 1) : 0; ?>%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة المبيعات ضمن الهدف -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-shopping-cart me-1"></i>
            المبيعات ضمن فترة الهدف
        </div>
        <div class="card-body">
            <?php if (count($sales) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>العميل</th>
                                <th>تاريخ البيع</th>
                                <th>المبلغ</th>
                                <th>العمولة</th>
                                <th>الحالة</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                    <td><?php echo formatMoney($sale_item['commission_amount']); ?></td>
                                    <td><span class="badge status-<?php echo $sale_item['payment_status']; ?>"><?php echo translateSaleStatus($sale_item['payment_status']); ?></span></td>
                                    <td>
                                        <a href="../sales/view.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../sales/edit.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../sales/index.php?action=delete&id=<?php echo $sale_item['id']; ?>" class="btn btn-danger btn-sm" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذه المبيعة؟');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات ضمن فترة هذا الهدف.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// إعداد الرسم البياني للمبيعات حسب الحالة
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('salesStatusChart').getContext('2d');
    var salesStatusChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار', 'ملغية'],
            datasets: [{
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
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>