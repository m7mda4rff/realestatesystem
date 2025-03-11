<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
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

// الحصول على معلومات العميل
$client->readOne($sale->client_id);

// الحصول على معلومات المندوب
$user->readOne($sale->salesperson_id);

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل المبيعة';

// تحديد الصفحة النشطة للقائمة الجانبية
$active_page = 'sales';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل المبيعة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">المبيعات</a></li>
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle me-1"></i>
                        تفاصيل المبيعة
                    </div>
                    <div>
                        <a href="edit.php?id=<?php echo $sale_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> تعديل
                        </a>
                        <a href="#" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash me-1"></i> حذف
                        </a>
                        <a href="print.php?id=<?php echo $sale_id; ?>" class="btn btn-secondary btn-sm" target="_blank">
                            <i class="fas fa-print me-1"></i> طباعة
                        </a>
                    </div>
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
                            <h5 class="card-title">معلومات الأطراف</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">العميل</th>
                                    <td>
                                        <a href="../clients/view.php?id=<?php echo $sale->client_id; ?>">
                                            <?php echo $client->name; ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">هاتف العميل</th>
                                    <td><?php echo $client->phone; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">مندوب المبيعات</th>
                                    <td>
                                        <a href="../users/view.php?id=<?php echo $sale->salesperson_id; ?>">
                                            <?php echo $user->full_name; ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">هاتف المندوب</th>
                                    <td><?php echo $user->phone; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo formatDate($sale->created_at); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">آخر تحديث</th>
                                    <td><?php echo $sale->updated_at ? formatDate($sale->updated_at) : 'لا يوجد'; ?></td>
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
        
        <!-- بطاقة المدفوعات والإجراءات -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-money-bill-wave me-1"></i>
                    تفاصيل الدفع
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th class="text-muted">طريقة الدفع</th>
                            <td>
                                <?php 
                                    switch($sale->payment_method) {
                                        case 'cash':
                                            echo 'نقداً';
                                            break;
                                        case 'bank_transfer':
                                            echo 'تحويل بنكي';
                                            break;
                                        case 'cheque':
                                            echo 'شيك';
                                            break;
                                        case 'installment':
                                            echo 'تقسيط';
                                            break;
                                        case 'other':
                                            echo 'أخرى';
                                            break;
                                        default:
                                            echo $sale->payment_method;
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">تفاصيل الدفع</th>
                            <td><?php echo $sale->payment_details ?: 'لا توجد تفاصيل'; ?></td>
                        </tr>
                    </table>
                    
                    <hr class="my-3">
                    
                    <!-- تغيير حالة الدفع -->
                    <h6 class="mb-3">تحديث حالة الدفع</h6>
                    <form method="post" action="update_status.php">
                        <input type="hidden" name="sale_id" value="<?php echo $sale_id; ?>">
                        <div class="mb-3">
                            <select class="form-select" name="payment_status">
                                <option value="paid" <?php echo ($sale->payment_status === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                                <option value="pending" <?php echo ($sale->payment_status === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                                <option value="cancelled" <?php echo ($sale->payment_status === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sync-alt me-1"></i> تحديث الحالة
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- بطاقة الإجراءات السريعة -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tools me-1"></i>
                    إجراءات سريعة
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../commissions/index.php?sale_id=<?php echo $sale_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-coins me-1"></i> إدارة العمولات
                        </a>
                        <a href="mailto:<?php echo $client->email; ?>" class="btn btn-outline-info">
                            <i class="fas fa-envelope me-1"></i> مراسلة العميل
                        </a>
                        <a href="mailto:<?php echo $user->email; ?>" class="btn btn-outline-info">
                            <i class="fas fa-envelope me-1"></i> مراسلة المندوب
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رغبتك في حذف هذه المبيعة؟</p>
                <p class="text-danger"><strong>تنبيه:</strong> هذا الإجراء لا يمكن التراجع عنه.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <a href="index.php?action=delete&id=<?php echo $sale_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> نعم، حذف
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>