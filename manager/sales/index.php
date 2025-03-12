<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
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

// الحصول على معرف المدير من الجلسة
$manager_id = $_SESSION['user_id'];

// الحصول على قائمة المندوبين التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);
$salespeople_ids = array_column($salespeople, 'id');

// إعدادات الفلترة
$filters = array();

// فلترة حسب المندوب
if (isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id'])) {
    $salesperson_id = (int)$_GET['salesperson_id'];
    
    // التحقق من أن المندوب يتبع للمدير الحالي
    if (in_array($salesperson_id, $salespeople_ids)) {
        $filters['salesperson_id'] = $salesperson_id;
    } else {
        $filters['salesperson_id'] = $salespeople_ids;
    }
} else {
    $filters['salesperson_id'] = $salespeople_ids;
}

// فلترة حسب العميل
if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $filters['client_id'] = (int)$_GET['client_id'];
}

// فلترة حسب حالة الدفع
if (isset($_GET['payment_status']) && !empty($_GET['payment_status'])) {
    $filters['payment_status'] = $_GET['payment_status'];
}

// فلترة حسب التاريخ
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

// البحث
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// الحصول على قائمة المبيعات
$sales_data = $sale->readAll($filters);

// حساب الإجماليات
$total_amount = 0;
$total_commission = 0;
$status_counts = array(
    'paid' => 0,
    'pending' => 0,
    'cancelled' => 0
);
$status_amounts = array(
    'paid' => 0,
    'pending' => 0,
    'cancelled' => 0
);

foreach ($sales_data as $sale_item) {
    $total_amount += $sale_item['amount'];
    $total_commission += $sale_item['commission_amount'];
    $status_counts[$sale_item['payment_status']]++;
    $status_amounts[$sale_item['payment_status']] += $sale_item['amount'];
}

// الحصول على قائمة العملاء للفلترة
$clients = $client->readAll();

// تعيين عنوان الصفحة
$page_title = 'متابعة المبيعات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">متابعة المبيعات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">متابعة المبيعات</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($_SESSION['success_message'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- بطاقات ملخص المبيعات -->
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
                            <i class="fas fa-chart-line fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-white">عدد المبيعات: <?php echo count($sales_data); ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($status_amounts['paid']); ?></h4>
                            <div class="small">المبيعات المدفوعة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-white">عدد المبيعات: <?php echo $status_counts['paid']; ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo formatMoney($status_amounts['pending']); ?></h4>
                            <div class="small">المبيعات قيد الانتظار</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <span class="small text-white">عدد المبيعات: <?php echo $status_counts['pending']; ?></span>
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
                    <span class="small text-white">متوسط العمولة: <?php echo count($sales_data) > 0 ? formatMoney($total_commission / count($sales_data)) : formatMoney(0); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة المبيعات
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="salesperson_id" class="form-label">المندوب</label>
                        <select class="form-select" id="salesperson_id" name="salesperson_id">
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
                        <select class="form-select" id="client_id" name="client_id">
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
                        <label for="search" class="form-label">بحث</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="ابحث...">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-end mb-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> بحث
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- بطاقة المبيعات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            قائمة المبيعات
        </div>
        <div class="card-body">
            <?php if (count($sales_data) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>المندوب</th>
                                <th>العميل</th>
                                <th>المبلغ</th>
                                <th>العمولة</th>
                                <th>تاريخ البيع</th>
                                <th>الحالة</th>
                                <th width="10%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_data as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                    <td><?php echo formatMoney($sale_item['commission_amount']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                            <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <a href="view.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-info btn-sm me-1" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-primary btn-sm change-status-btn" 
                                                    data-id="<?php echo $sale_item['id']; ?>" 
                                                    data-status="<?php echo $sale_item['payment_status']; ?>" title="تغيير الحالة">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات متاحة.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- مخططات المبيعات -->
    <?php if (count($sales_data) > 0) : ?>
        <div class="row">
            <div class="col-xl-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-pie me-1"></i>
                        توزيع المبيعات حسب الحالة
                    </div>
                    <div class="card-body">
                        <canvas id="salesStatusChart" width="100%" height="40"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        المبيعات حسب المندوب
                    </div>
                    <div class="card-body">
                        <canvas id="salespersonChart" width="100%" height="40"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- نموذج تغيير حالة المبيعة -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="change-status-form" method="post" action="change_status.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">تغيير حالة المبيعة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="sale_id" id="sale_id">
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="paid">مدفوعة</option>
                            <option value="pending">قيد الانتظار</option>
                            <option value="cancelled">ملغية</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" id="status-info">
                        <i class="fas fa-info-circle me-1"></i> تغيير حالة المبيعة سيؤثر على حالة العمولة المرتبطة بها.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($sales_data) > 0) : ?>
    // بيانات توزيع المبيعات حسب الحالة
    var statusCtx = document.getElementById('salesStatusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مدفوعة', 'قيد الانتظار', 'ملغية'],
            datasets: [{
                data: [
                    <?php echo $status_counts['paid']; ?>,
                    <?php echo $status_counts['pending']; ?>,
                    <?php echo $status_counts['cancelled']; ?>
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
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    // بيانات المبيعات حسب المندوب
    var spCtx = document.getElementById('salespersonChart').getContext('2d');
    
    // تجميع البيانات حسب المندوب
    var salesBySalesperson = {};
    <?php foreach ($salespeople as $sp) : ?>
        salesBySalesperson['<?php echo $sp['id']; ?>'] = {
            name: '<?php echo htmlspecialchars($sp['full_name']); ?>',
            amount: 0
        };
    <?php endforeach; ?>
    
    <?php foreach ($sales_data as $sale_item) : ?>
        if (salesBySalesperson['<?php echo $sale_item['salesperson_id']; ?>']) {
            salesBySalesperson['<?php echo $sale_item['salesperson_id']; ?>'].amount += <?php echo $sale_item['amount']; ?>;
        }
    <?php endforeach; ?>
    
    var spData = Object.values(salesBySalesperson);
    
    var spChart = new Chart(spCtx, {
        type: 'bar',
        data: {
            labels: spData.map(function(item) { return item.name; }),
            datasets: [{
                label: 'إجمالي المبيعات (ج.م)',
                data: spData.map(function(item) { return item.amount; }),
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    
    // تهيئة نموذج تغيير الحالة
    var changeStatusButtons = document.querySelectorAll('.change-status-btn');
    var changeStatusModal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
    
    changeStatusButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var saleId = this.getAttribute('data-id');
            var currentStatus = this.getAttribute('data-status');
            
            document.getElementById('sale_id').value = saleId;
            
            var statusSelect = document.getElementById('new_status');
            statusSelect.value = currentStatus;
            
            changeStatusModal.show();
        });
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>