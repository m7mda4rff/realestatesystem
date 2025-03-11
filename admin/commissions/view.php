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

// الحصول على معلومات المبيعة المرتبطة
$sale->readOne($commission->sale_id);

// الحصول على معلومات المندوب
$user->readOne($commission->salesperson_id);

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل العمولة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل العمولة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">العمولات</a></li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle me-1"></i>
                        تفاصيل العمولة
                    </div>
                    <div>
                        <?php if ($commission->status === 'pending') : ?>
                            <a href="pay_commission.php?id=<?php echo $commission_id; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-money-bill-wave me-1"></i> تسجيل الدفع
                            </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> عودة للقائمة
                        </a>
                    </div>
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
                                    <th class="text-muted">مندوب المبيعات</th>
                                    <td>
                                        <a href="../users/view.php?id=<?php echo $user->id; ?>">
                                            <?php echo htmlspecialchars($user->full_name); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">قيمة العمولة</th>
                                    <td><?php echo formatMoney($commission->amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة العمولة</th>
                                    <td>
                                        <span class="badge status-<?php echo $commission->status; ?>">
                                            <?php echo translateCommissionStatus($commission->status); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الدفع</th>
                                    <td><?php echo $commission->payment_date ? formatDate($commission->payment_date) : 'لم يتم الدفع بعد'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">ملاحظات</th>
                                    <td><?php echo nl2br(htmlspecialchars($commission->notes)) ?: 'لا توجد ملاحظات'; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات المبيعة المرتبطة</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم المبيعة</th>
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
                                <tr>
                                    <th class="text-muted">قيمة المبيعة</th>
                                    <td><?php echo formatMoney($sale->amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">نسبة العمولة</th>
                                    <td><?php echo $sale->commission_rate; ?>%</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة المبيعة</th>
                                    <td>
                                        <span class="badge status-<?php echo $sale->payment_status; ?>">
                                            <?php echo translateSaleStatus($sale->payment_status); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة الإجراءات والتحديثات -->
        <div class="col-lg-4">
            <!-- بطاقة تحديث حالة العمولة -->
            <?php if ($commission->status === 'pending') : ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-money-bill-wave me-1"></i>
                        تسجيل دفع العمولة
                    </div>
                    <div class="card-body">
                        <form method="post" action="update_status.php">
                            <input type="hidden" name="commission_id" value="<?php echo $commission_id; ?>">
                            <input type="hidden" name="status" value="paid">
                            
                            <div class="mb-3">
                                <label for="payment_notes" class="form-label">ملاحظات الدفع</label>
                                <textarea class="form-control" id="payment_notes" name="payment_notes" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-1"></i> تأكيد دفع العمولة
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
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
                    
                    <div class="d-grid gap-2 mt-3">
                        <a href="../users/view.php?id=<?php echo $user->id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-user me-1"></i> عرض ملف المندوب
                        </a>
                        <a href="index.php?salesperson_id=<?php echo $user->id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-coins me-1"></i> عرض جميع عمولات المندوب
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>