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
require_once '../../classes/Commission.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);
$client = new Client($conn);
$commission = new Commission($conn);

// الحصول على معرف المدير من الجلسة
$manager_id = $_SESSION['user_id'];

// التحقق من وجود معرف المبيعة في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المبيعة غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المبيعة
$sale_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل المبيعة
if (!$sale->readOne($sale_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المبيعة المطلوبة';
    header('Location: index.php');
    exit;
}

// الحصول على قائمة المندوبين التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);
$salespeople_ids = array_column($salespeople, 'id');

// التحقق من أن المبيعة تخص أحد مندوبي المدير
if (!in_array($sale->salesperson_id, $salespeople_ids)) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية الوصول إلى هذه المبيعة';
    header('Location: index.php');
    exit;
}

// الحصول على معلومات العميل
$client->readOne($sale->client_id);

// الحصول على معلومات المندوب
$user->readOne($sale->salesperson_id);

// الحصول على معلومات العمولة المرتبطة بالمبيعة
$commission_data = [];
$commission_query = "SELECT * FROM commissions WHERE sale_id = ?";
$stmt = $conn->prepare($commission_query);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $commission_data = $result->fetch_assoc();
}

// معالجة تغيير حالة المبيعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $new_status = $_POST['new_status'];
    
    // التحقق من صحة الحالة
    if (in_array($new_status, ['paid', 'pending', 'cancelled'])) {
        if ($sale->changeStatus($sale_id, $new_status)) {
            $_SESSION['success_message'] = 'تم تحديث حالة المبيعة بنجاح';
            // إعادة قراءة بيانات المبيعة بعد التحديث
            $sale->readOne($sale_id);
        } else {
            $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة المبيعة';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل المبيعة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل المبيعة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">متابعة المبيعات</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل المبيعة #<?php echo $sale_id; ?></li>
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
    
    <div class="row">
        <!-- بطاقة تفاصيل المبيعة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    تفاصيل المبيعة
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات أساسية</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم المبيعة</th>
                                    <td>#<?php echo $sale_id; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ البيع</th>
                                    <td><?php echo formatDate($sale->sale_date); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">المبلغ</th>
                                    <td><?php echo formatMoney($sale->amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">نسبة العمولة</th>
                                    <td><?php echo $sale->commission_rate; ?>%</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">قيمة العمولة</th>
                                    <td><?php echo formatMoney($sale->commission_amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة الدفع</th>
                                    <td>
                                        <span class="badge status-<?php echo $sale->payment_status; ?>">
                                            <?php echo translateSaleStatus($sale->payment_status); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات العميل</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">اسم العميل</th>
                                    <td><?php echo htmlspecialchars($client->name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">البريد الإلكتروني</th>
                                    <td><?php echo htmlspecialchars($client->email); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">رقم الهاتف</th>
                                    <td><?php echo htmlspecialchars($client->phone); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">العنوان</th>
                                    <td><?php echo htmlspecialchars($client->address); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات المندوب</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">اسم المندوب</th>
                                    <td><?php echo htmlspecialchars($user->full_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">البريد الإلكتروني</th>
                                    <td><?php echo htmlspecialchars($user->email); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">رقم الهاتف</th>
                                    <td><?php echo htmlspecialchars($user->phone ?? 'غير متوفر'); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo date('Y-m-d', strtotime($sale->created_at)); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">بيانات المبيعة</h5>
                            <div class="mb-3">
                                <label class="text-muted">الوصف</label>
                                <p><?php echo nl2br(htmlspecialchars($sale->description)) ?: 'لا يوجد وصف'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة حالة العمولة -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-coins me-1"></i>
                    حالة العمولة
                </div>
                <div class="card-body">
                    <?php if (!empty($commission_data)) : ?>
                        <div class="mb-4 text-center">
                            <h5>قيمة العمولة</h5>
                            <h2><?php echo formatMoney($commission_data['amount']); ?></h2>
                            <span class="badge status-<?php echo $commission_data['status']; ?> mt-2 fs-6">
                                <?php echo translateCommissionStatus($commission_data['status']); ?>
                            </span>
                        </div>
                        
                        <hr>
                        
                        <table class="table table-borderless">
                            <tr>
                                <th class="text-muted">تاريخ الدفع</th>
                                <td>
                                    <?php echo $commission_data['payment_date'] ? formatDate($commission_data['payment_date']) : 'لم يتم الدفع بعد'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-muted">نسبة العمولة</th>
                                <td><?php echo $sale->commission_rate; ?>%</td>
                            </tr>
                            <tr>
                                <th class="text-muted">ملاحظات</th>
                                <td><?php echo nl2br(htmlspecialchars($commission_data['notes'])) ?: 'لا توجد ملاحظات'; ?></td>
                            </tr>
                        </table>
                    <?php else : ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i> لا توجد معلومات عمولة متاحة لهذه المبيعة.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- بطاقة الإجراءات -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-edit me-1"></i>
                    الإجراءات المتاحة
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                            <i class="fas fa-exchange-alt me-1"></i> تغيير حالة المبيعة
                        </button>
                        
                        <a href="../salespeople/view.php?id=<?php echo $sale->salesperson_id; ?>" class="btn btn-success">
                            <i class="fas fa-user me-1"></i> عرض بيانات المندوب
                        </a>
                        
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> العودة إلى قائمة المبيعات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نموذج تغيير حالة المبيعة -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $sale_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">تغيير حالة المبيعة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">الحالة الجديدة</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="paid" <?php echo ($sale->payment_status === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                            <option value="pending" <?php echo ($sale->payment_status === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                            <option value="cancelled" <?php echo ($sale->payment_status === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-1"></i> تغيير حالة المبيعة سيؤثر على حالة العمولة المرتبطة بها.
                        <?php if ($sale->payment_status !== 'paid' && !empty($commission_data)) : ?>
                            <br>
                            <strong>ملاحظة:</strong> تغيير الحالة إلى "مدفوعة" سيؤدي إلى تغيير حالة العمولة إلى "مدفوعة" أيضاً.
                        <?php endif; ?>
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

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>