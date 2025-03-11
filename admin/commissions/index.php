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
require_once '../../classes/Commission.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$commission = new Commission($conn);
$user = new User($conn);

// التحقق من عمليات تحديث الحالة المتعددة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_multiple') {
    if (isset($_POST['commission_ids']) && is_array($_POST['commission_ids']) && !empty($_POST['commission_ids'])) {
        if ($commission->updateMultiple($_POST['commission_ids'], 'paid')) {
            $_SESSION['success_message'] = 'تم تحديث حالة العمولات المحددة بنجاح';
        } else {
            $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة العمولات';
        }
    } else {
        $_SESSION['error_message'] = 'يرجى تحديد عمولة واحدة على الأقل';
    }
}

// إعدادات الفلترة والتصفح
$filters = [];

// فلترة حسب حالة العمولة
if (isset($_GET['status']) && in_array($_GET['status'], ['paid', 'pending'])) {
    $filters['status'] = $_GET['status'];
}

// فلترة حسب مندوب المبيعات
if (isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id'])) {
    $filters['salesperson_id'] = (int)$_GET['salesperson_id'];
}

// فلترة حسب تاريخ البدء والانتهاء
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

// الحصول على قائمة مندوبي المبيعات للفلترة
$salespeople = $user->readAll('salesperson');

// الحصول على قائمة العمولات حسب الفلاتر
$commissions = [];

// جلب جميع العمولات إذا لم يكن هناك فلترة خاصة
if (empty($filters['salesperson_id'])) {
    // ربط معلمات الاستعلام حسب الفلاتر
    $query_params = [];
    
    if (!empty($filters['status'])) {
        $query_params['status'] = $filters['status'];
    }
    
    // استعلام جميع العمولات
    $sql = "SELECT c.*, s.sale_date, s.amount as sale_amount, u.full_name as salesperson_name, cl.name as client_name 
            FROM commissions c
            LEFT JOIN sales s ON c.sale_id = s.id
            LEFT JOIN users u ON c.salesperson_id = u.id
            LEFT JOIN clients cl ON s.client_id = cl.id";
    
    // إضافة شروط الفلترة
    $where_clauses = [];
    if (!empty($filters['status'])) {
        $where_clauses[] = "c.status = '" . $filters['status'] . "'";
    }
    
    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
        $where_clauses[] = "s.sale_date BETWEEN '" . $filters['start_date'] . "' AND '" . $filters['end_date'] . "'";
    } elseif (!empty($filters['start_date'])) {
        $where_clauses[] = "s.sale_date >= '" . $filters['start_date'] . "'";
    } elseif (!empty($filters['end_date'])) {
        $where_clauses[] = "s.sale_date <= '" . $filters['end_date'] . "'";
    }
    
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // ترتيب النتائج
    $sql .= " ORDER BY c.id DESC";
    
    // تنفيذ الاستعلام
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $commissions[] = $row;
        }
    }
} else {
    // إذا تم تحديد مندوب مبيعات محدد
    $commissions = $commission->getCommissionsBySalesperson($filters['salesperson_id'], $filters['status'] ?? null);
}

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
$page_title = 'إدارة العمولات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة العمولات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة العمولات</li>
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
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة العمولات
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">حالة العمولة</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">الكل</option>
                            <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="salesperson_id" class="form-label">مندوب المبيعات</label>
                        <select class="form-select" id="salesperson_id" name="salesperson_id">
                            <option value="">الكل</option>
                            <?php foreach ($salespeople as $person) : ?>
                                <option value="<?php echo $person['id']; ?>" <?php echo (isset($_GET['salesperson_id']) && $_GET['salesperson_id'] == $person['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($person['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                    </div>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> بحث
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
    </div>
    
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
    
    <!-- قائمة العمولات -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-coins me-1"></i>
                قائمة العمولات
            </div>
            <div>
                <a href="reports.php" class="btn btn-success btn-sm">
                    <i class="fas fa-chart-bar me-1"></i> تقارير العمولات
                </a>
            </div>
        </div>
        <div class="card-body">
            <form id="commissions-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <input type="hidden" name="action" value="update_multiple">
                
                <?php if (count($commissions) > 0) : ?>
                    <!-- أزرار التحديث الجماعي -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-success btn-sm" id="mark-paid-btn" disabled>
                            <i class="fas fa-check-circle me-1"></i> تحديد المحدد كمدفوع
                        </button>
                    </div>
                
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable">
                            <thead>
                                <tr>
                                    <th width="5%">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th width="5%">#</th>
                                    <th>المندوب</th>
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
                                        <td>
                                            <?php if ($comm['status'] === 'pending') : ?>
                                                <div class="form-check">
                                                    <input class="form-check-input commission-checkbox" type="checkbox" name="commission_ids[]" value="<?php echo $comm['id']; ?>">
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($comm['salesperson_name']); ?></td>
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
                                            <a href="view.php?id=<?php echo $comm['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($comm['status'] === 'pending') : ?>
                                                <a href="pay_commission.php?id=<?php echo $comm['id']; ?>" class="btn btn-success btn-sm" title="تسجيل الدفع">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> لا توجد عمولات متاحة.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تفعيل/تعطيل زر "تحديد المحدد كمدفوع" بناءً على الاختيارات
    const checkboxes = document.querySelectorAll('.commission-checkbox');
    const selectAllCheckbox = document.getElementById('select-all');
    const markPaidBtn = document.getElementById('mark-paid-btn');
    
    // تحديث حالة الزر بناءً على الاختيارات
    function updateButtonState() {
        let hasChecked = false;
        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                hasChecked = true;
            }
        });
        markPaidBtn.disabled = !hasChecked;
    }
    
    // إضافة مستمع حدث لكل صندوق اختيار
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', updateButtonState);
    });
    
    // تحديد/إلغاء تحديد الكل
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = isChecked;
            });
            updateButtonState();
        });
    }
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>