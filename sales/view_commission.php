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
require_once '../classes/Client.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$commission = new Commission($conn);
$sale = new Sale($conn);
$client = new Client($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// التحقق من وجود معرف العمولة في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف العمولة غير صحيح';
    header('Location: mycommissions.php');
    exit;
}

// الحصول على معرف العمولة
$commission_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل العمولة
$commission_query = "SELECT c.*, s.amount as sale_amount, s.sale_date, s.client_id, s.description as sale_description 
                     FROM commissions c 
                     LEFT JOIN sales s ON c.sale_id = s.id 
                     WHERE c.id = ? AND c.salesperson_id = ?";
$stmt = $conn->prepare($commission_query);
$stmt->bind_param("ii", $commission_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'لم يتم العثور على العمولة المطلوبة أو ليس لديك صلاحية الوصول إليها';
    header('Location: mycommissions.php');
    exit;
}

$commission_data = $result->fetch_assoc();
$sale_id = $commission_data['sale_id'];

// الحصول على معلومات العميل
$client->readOne($commission_data['client_id']);

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل العمولة';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل العمولة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="mycommissions.php">عمولاتي</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل العمولة #<?php echo $commission_id; ?></li>
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
        <!-- بطاقة تفاصيل العمولة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    تفاصيل العمولة
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات العمولة</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم العمولة</th>
                                    <td>#<?php echo $commission_id; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">قيمة العمولة</th>
                                    <td><?php echo formatMoney($commission_data['amount']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة العمولة</th>
                                    <td>
                                        <span class="badge status-<?php echo $commission_data['status']; ?>">
                                            <?php echo translateCommissionStatus($commission_data['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo formatDate($commission_data['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الدفع</th>
                                    <td><?php echo $commission_data['payment_date'] ? formatDate($commission_data['payment_date']) : 'لم يتم الدفع بعد'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">ملاحظات</th>
                                    <td><?php echo nl2br(htmlspecialchars($commission_data['notes'])) ?: 'لا توجد ملاحظات'; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات المبيعة المرتبطة</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم المبيعة</th>
                                    <td>
                                        <a href="view_sale.php?id=<?php echo $sale_id; ?>">
                                            #<?php echo $sale_id; ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ البيع</th>
                                    <td><?php echo formatDate($commission_data['sale_date']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">قيمة المبيعة</th>
                                    <td><?php echo formatMoney($commission_data['sale_amount']); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">نسبة العمولة</th>
                                    <td>
                                        <?php
                                            $commission_rate = ($commission_data['amount'] / $commission_data['sale_amount']) * 100;
                                            echo number_format($commission_rate, 2) . '%';
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">اسم العميل</th>
                                    <td><?php echo htmlspecialchars($client->name); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="card-title">وصف المبيعة</h5>
                            <p><?php echo nl2br(htmlspecialchars($commission_data['sale_description'])) ?: 'لا يوجد وصف'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة الملخص والإجراءات -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-coins me-1"></i>
                    ملخص العمولة
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2><?php echo formatMoney($commission_data['amount']); ?></h2>
                        <div class="text-muted">قيمة العمولة</div>
                        
                        <div class="mt-3">
                            <span class="badge status-<?php echo $commission_data['status']; ?> fs-6">
                                <?php echo translateCommissionStatus($commission_data['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="progress mb-3" style="height: 10px;">
                        <?php 
                            $progress = $commission_data['status'] === 'paid' ? 100 : 50;
                            $color = $commission_data['status'] === 'paid' ? 'success' : 'warning';
                        ?>
                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    
                    <?php if ($commission_data['status'] === 'pending') : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> هذه العمولة قيد الانتظار. سيتم إشعارك عندما يتم دفعها.
                        </div>
                    <?php else : ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-1"></i> تم دفع هذه العمولة بتاريخ 
                            <?php echo formatDate($commission_data['payment_date']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 mt-4">
                        <a href="mycommissions.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-1"></i> العودة إلى العمولات
                        </a>
                        <a href="view_sale.php?id=<?php echo $sale_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye me-1"></i> عرض تفاصيل المبيعة
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- معلومات إضافية -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-lightbulb me-1"></i>
                    معلومات مفيدة
                </div>
                <div class="card-body">
                    <h6>كيف يتم احتساب العمولات؟</h6>
                    <p>يتم احتساب العمولات بناءً على نسبة محددة من قيمة المبيعة. النسبة الافتراضية هي <?php echo COMMISSION_DEFAULT_RATE ?? 2.5; ?>%.</p>
                    
                    <h6>متى يتم دفع العمولات؟</h6>
                    <p>يتم دفع العمولات بعد تحصيل قيمة المبيعة من العميل، وعادةً ما تكون في نهاية الشهر.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>