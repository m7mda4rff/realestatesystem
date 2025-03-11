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
require_once '../classes/Client.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$client = new Client($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// إعدادات الفلترة والتصفح
$filters = ['salesperson_id' => $user_id];

// فلترة حسب حالة الدفع
if (isset($_GET['status']) && in_array($_GET['status'], ['paid', 'pending', 'cancelled'])) {
    $filters['payment_status'] = $_GET['status'];
}

// فلترة حسب العميل
if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
    $filters['client_id'] = (int)$_GET['client_id'];
}

// فلترة حسب تاريخ البدء والانتهاء
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

// الحصول على قائمة المبيعات حسب الفلاتر
$sales = $sale->readAll($filters);

// الحصول على قائمة العملاء للفلترة
$clients = $client->readAll();

// تعيين عنوان الصفحة
$page_title = 'مبيعاتي';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">مبيعاتي</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">مبيعاتي</li>
    </ol>
    
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
                        <label for="status" class="form-label">حالة الدفع</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">الكل</option>
                            <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                            <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
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
                        <a href="mysales.php" class="btn btn-secondary">
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
            <i class="fas fa-shopping-cart me-1"></i>
            قائمة المبيعات
        </div>
        <div class="card-body">
            <?php if (count($sales) > 0) : ?>
                <!-- ملخص المبيعات -->
                <?php
                    $total_amount = 0;
                    $total_commission = 0;
                    $status_counts = ['paid' => 0, 'pending' => 0, 'cancelled' => 0];
                    
                    foreach ($sales as $sale_item) {
                        $total_amount += $sale_item['amount'];
                        $total_commission += $sale_item['commission_amount'];
                        $status_counts[$sale_item['payment_status']]++;
                    }
                ?>
                
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">إجمالي المبيعات</h5>
                                <p class="card-text h4"><?php echo formatMoney($total_amount); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">إجمالي العمولات</h5>
                                <p class="card-text h4"><?php echo formatMoney($total_commission); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">عدد المبيعات</h5>
                                <p class="card-text h4"><?php echo count($sales); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">متوسط قيمة المبيعة</h5>
                                <p class="card-text h4"><?php echo formatMoney($total_amount / max(1, count($sales))); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- جدول المبيعات -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>العميل</th>
                                <th>المبلغ</th>
                                <th>العمولة</th>
                                <th>تاريخ البيع</th>
                                <th>الحالة</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
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
                                        <a href="view_sale.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
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
                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات متاحة وفقاً للفلاتر المحددة.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>