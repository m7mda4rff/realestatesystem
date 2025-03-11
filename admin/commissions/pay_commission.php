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
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$commission = new Commission($conn);
$user = new User($conn);
$sale = new Sale($conn);

// التحقق من وجود معرف العمولة في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف العمولة غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف العمولة
$commission_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل العمولة
if (!$commission->readOne($commission_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على العمولة المطلوبة';
    header('Location: index.php');
    exit;
}

// التحقق من أن العمولة في حالة "قيد الانتظار"
if ($commission->status !== 'pending') {
    $_SESSION['error_message'] = 'لا يمكن دفع عمولة سبق دفعها';
    header('Location: view.php?id=' . $commission_id);
    exit;
}

// الحصول على معلومات المبيعة المرتبطة
$sale->readOne($commission->sale_id);

// الحصول على معلومات المندوب
$user->readOne($commission->salesperson_id);

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام البيانات من النموذج
    $payment_date = isset($_POST['payment_date']) && !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $payment_notes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';
    
    // تحديث العمولة
    $commission->status = 'paid';
    $commission->payment_date = $payment_date;
    $commission->notes = $payment_notes . "\n\nطريقة الدفع: " . $payment_method;
    
    if ($commission->updateStatus()) {
        $_SESSION['success_message'] = 'تم تسجيل دفع العمولة بنجاح';
        header('Location: view.php?id=' . $commission_id);
        exit;
    } else {
        $_SESSION['error_message'] = 'حدث خطأ أثناء تسجيل دفع العمولة';
    }
}

// تعيين عنوان الصفحة
$page_title = 'تسجيل دفع العمولة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تسجيل دفع العمولة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">العمولات</a></li>
        <li class="breadcrumb-item"><a href="view.php?id=<?php echo $commission_id; ?>">عرض تفاصيل العمولة #<?php echo $commission_id; ?></a></li>
        <li class="breadcrumb-item active">تسجيل الدفع</li>
    </ol>
    
    <!-- رسائل الخطأ -->
    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-money-bill-wave me-1"></i>
                    تسجيل دفع العمولة
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $commission_id); ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5 class="mb-3">معلومات العمولة</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <th class="text-muted">مندوب المبيعات</th>
                                        <td><?php echo htmlspecialchars($user->full_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">قيمة العمولة</th>
                                        <td><?php echo formatMoney($commission->amount); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">رقم المبيعة</th>
                                        <td>
                                            <a href="../sales/view.php?id=<?php echo $sale->id; ?>">
                                                #<?php echo $sale->id; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">تاريخ البيع</th>
                                        <td><?php echo formatDate($sale->sale_date); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-3">تفاصيل الدفع</h5>
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">تاريخ الدفع</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">طريقة الدفع</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="cash">نقداً</option>
                                        <option value="bank_transfer">تحويل بنكي</option>
                                        <option value="cheque">شيك</option>
                                        <option value="other">أخرى</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="view.php?id=<?php echo $commission_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> إلغاء
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-circle me-1"></i> تأكيد دفع العمولة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- بطاقة معلومات المندوب -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    معلومات المندوب
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="mb-2">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h5><?php echo htmlspecialchars($user->full_name); ?></h5>
                    </div>
                    
                    <table class="table table-borderless">
                        <tr>
                            <th class="text-muted">البريد الإلكتروني</th>
                            <td><?php echo htmlspecialchars($user->email); ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">الهاتف</th>
                            <td><?php echo htmlspecialchars($user->phone) ?: 'غير محدد'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>