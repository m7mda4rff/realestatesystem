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
require_once '../classes/Commission.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$client = new Client($conn);
$commission = new Commission($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// التحقق من وجود معرف المبيعة في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المبيعة غير صحيح';
    header('Location: mysales.php');
    exit;
}

// الحصول على معرف المبيعة
$sale_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل المبيعة
if (!$sale->readOne($sale_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المبيعة المطلوبة';
    header('Location: mysales.php');
    exit;
}

// التحقق إذا كانت المبيعة تخص المندوب الحالي
if ($sale->salesperson_id != $user_id) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية الوصول إلى هذه المبيعة';
    header('Location: mysales.php');
    exit;
}

// الحصول على معلومات العميل
$client->readOne($sale->client_id);

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

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل المبيعة';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل المبيعة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="mysales.php">مبيعاتي</a></li>
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
                        <div class="col-md-12">
                            <h5 class="card-title">بيانات المبيعة</h5>
                            <div class="mb-3">
                                <label class="text-muted">الوصف</label>
                                <p><?php echo nl2br(htmlspecialchars($sale->description)) ?: 'لا يوجد وصف'; ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted">ملاحظات</label>
                                <p><?php echo nl2br(htmlspecialchars($sale->notes)) ?: 'لا توجد ملاحظات'; ?></p>
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
                    
                    <div class="mt-3">
                        <a href="mycommissions.php" class="btn btn-primary w-100">
                            <i class="fas fa-coins me-1"></i> عرض جميع العمولات
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- روابط سريعة -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-link me-1"></i>
                    روابط سريعة
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="mysales.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-1"></i> العودة إلى المبيعات
                        </a>
                        <?php if (!empty($client->email)) : ?>
                            <a href="mailto:<?php echo $client->email; ?>" class="btn btn-outline-info">
                                <i class="fas fa-envelope me-1"></i> مراسلة العميل
                            </a>
                        <?php endif; ?>
                        <a href="print_sale.php?id=<?php echo $sale_id; ?>" class="btn btn-outline-secondary" target="_blank">
                            <i class="fas fa-print me-1"></i> طباعة تفاصيل المبيعة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>