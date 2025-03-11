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
require_once '../classes/Commission.php';
require_once '../classes/Sale.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$commission = new Commission($conn);
$sale = new Sale($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// فلترة حسب حالة العمولة
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// الحصول على عمولات المندوب
$commissions = $commission->getCommissionsBySalesperson($user_id, $status_filter);

// حساب إجماليات العمولات
$total_commissions = 0;
$pending_commissions = 0;
$paid_commissions = 0;

foreach ($commissions as $comm) {
    $total_commissions += $comm['amount'];
    if ($comm['status'] === 'pending') {
        $pending_commissions += $comm['amount'];
    } else if ($comm['status'] === 'paid') {
        $paid_commissions += $comm['amount'];
    }
}

// تعيين عنوان الصفحة
$page_title = 'عمولاتي';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عمولاتي</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">عمولاتي</li>
    </ol>
    
    <!-- ملخص العمولات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">إجمالي العمولات</div>
                            <div class="display-6"><?php echo formatMoney($total_commissions); ?></div>
                        </div>
                        <div><i class="fas fa-coins fa-3x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">العمولات المدفوعة</div>
                            <div class="display-6"><?php echo formatMoney($paid_commissions); ?></div>
                        </div>
                        <div><i class="fas fa-check-circle fa-3x"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small">العمولات المعلقة</div>
                            <div class="display-6"><?php echo formatMoney($pending_commissions); ?></div>
                        </div>
                        <div><i class="fas fa-clock fa-3x"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- فلترة العمولات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة العمولات
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="btn-group" role="group" aria-label="فلترة العمولات">
                    <a href="mycommissions.php" class="btn btn-outline-primary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                        الجميع
                    </a>
                    <a href="mycommissions.php?status=paid" class="btn btn-outline-primary <?php echo $status_filter === 'paid' ? 'active' : ''; ?>">
                        المدفوعة
                    </a>
                    <a href="mycommissions.php?status=pending" class="btn btn-outline-primary <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                        قيد الانتظار
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- قائمة العمولات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-coins me-1"></i>
            قائمة العمولات
        </div>
        <div class="card-body">
            <?php if (count($commissions) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>رقم المبيعة</th>
                                <th>العميل</th>
                                <th>تاريخ البيع</th>
                                <th>قيمة المبيعة</th>
                                <th>قيمة العمولة</th>
                                <th>حالة العمولة</th>
                                <th>تاريخ الدفع</th>
                                <th width="10%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commissions as $index => $comm) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <a href="view_sale.php?id=<?php echo $comm['sale_id']; ?>">
                                            #<?php echo $comm['sale_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($comm['client_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($comm['sale_date'])); ?></td>
                                    <td><?php echo formatMoney($comm['sale_amount']); ?></td>
                                    <td><?php echo formatMoney($comm['amount']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $comm['status']; ?>">
                                            <?php echo translateCommissionStatus($comm['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $comm['payment_date'] ? date('Y-m-d', strtotime($comm['payment_date'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <a href="view_commission.php?id=<?php echo $comm['id']; ?>" class="btn btn-info btn-sm" title="عرض">
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
                    <i class="fas fa-info-circle me-1"></i> لا توجد عمولات متاحة<?php echo $status_filter ? ' بالحالة المحددة' : ''; ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- الرسم البياني لتوزيع العمولات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-pie me-1"></i>
            توزيع العمولات
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <canvas id="commissionStatusChart" width="100%" height="40"></canvas>
                </div>
                <div class="col-md-6">
                    <canvas id="commissionMonthlyChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تهيئة الرسوم البيانية عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني لتوزيع حالة العمولات
    var statusCtx = document.getElementById('commissionStatusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار'],
            datasets: [{
                data: [<?php echo $paid_commissions; ?>, <?php echo $pending_commissions; ?>],
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
    
    // رسم بياني للعمولات الشهرية - هنا يمكن إضافة بيانات شهرية حقيقية
    var monthlyCtx = document.getElementById('commissionMonthlyChart').getContext('2d');
    var monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
            datasets: [{
                label: 'العمولات الشهرية',
                data: [
                    <?php echo rand(1000, 5000); ?>,
                    <?php echo rand(1000, 5000); ?>,
                    <?php echo rand(1000, 5000); ?>,
                    <?php echo rand(1000, 5000); ?>,
                    <?php echo rand(1000, 5000); ?>,
                    <?php echo $total_commissions; ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'العمولات الشهرية'
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
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>