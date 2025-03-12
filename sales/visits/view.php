<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومندوب مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Visit.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة الزيارات
$visit = new Visit($conn);

// التحقق من وجود معرف الزيارة في العنوان
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف الزيارة غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف الزيارة
$visit_id = (int)$_GET['id'];

// قراءة بيانات الزيارة
if (!$visit->readOne($visit_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على الزيارة المطلوبة';
    header('Location: index.php');
    exit;
}

// التحقق من أن الزيارة تنتمي للمندوب الحالي
if ($visit->salesperson_id !== $_SESSION['user_id']) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية الوصول إلى هذه الزيارة';
    header('Location: index.php');
    exit;
}

// معالجة تغيير حالة الزيارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $status = $_POST['status'];
    $outcome = isset($_POST['outcome']) ? $_POST['outcome'] : '';
    
    if ($visit->updateStatus($visit_id, $status, $outcome)) {
        $_SESSION['success_message'] = 'تم تحديث حالة الزيارة بنجاح';
        // إعادة قراءة بيانات الزيارة بعد التحديث
        $visit->readOne($visit_id);
    } else {
        $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة الزيارة';
    }
}

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل الزيارة';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل الزيارة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">الزيارات الخارجية</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل الزيارة #<?php echo $visit_id; ?></li>
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
        <!-- بطاقة تفاصيل الزيارة -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    تفاصيل الزيارة
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات الزيارة</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">رقم الزيارة</th>
                                    <td>#<?php echo $visit_id; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الزيارة</th>
                                    <td><?php echo date('Y-m-d', strtotime($visit->visit_time)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">وقت الزيارة</th>
                                    <td><?php echo date('h:i A', strtotime($visit->visit_time)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">حالة الزيارة</th>
                                    <td>
                                        <?php if ($visit->visit_status === 'planned') : ?>
                                            <span class="badge visit-planned">مخططة</span>
                                        <?php elseif ($visit->visit_status === 'completed') : ?>
                                            <span class="badge visit-completed">مكتملة</span>
                                        <?php else : ?>
                                            <span class="badge visit-cancelled">ملغية</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo date('Y-m-d', strtotime($visit->created_at)); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="card-title">معلومات العميل</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">اسم الشركة</th>
                                    <td><?php echo htmlspecialchars($visit->company_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">اسم العميل</th>
                                    <td><?php echo htmlspecialchars($visit->client_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">رقم الهاتف</th>
                                    <td><?php echo htmlspecialchars($visit->client_phone); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="card-title">تفاصيل الزيارة</h5>
                            <div class="mb-3">
                                <label class="text-muted">الغرض من الزيارة</label>
                                <p><?php echo nl2br(htmlspecialchars($visit->purpose)); ?></p>
                            </div>
                            
                            <?php if ($visit->visit_status === 'completed' && !empty($visit->outcome)) : ?>
                                <div class="mb-3">
                                    <label class="text-muted">نتيجة الزيارة</label>
                                    <p><?php echo nl2br(htmlspecialchars($visit->outcome)); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($visit->notes)) : ?>
                                <div class="mb-3">
                                    <label class="text-muted">ملاحظات إضافية</label>
                                    <p><?php echo nl2br(htmlspecialchars($visit->notes)); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة الإجراءات -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-edit me-1"></i>
                    الإجراءات المتاحة
                </div>
                <div class="card-body">
                    <?php if ($visit->visit_status === 'planned') : ?>
                        <div class="mb-4">
                            <h5 class="card-title">تحديث حالة الزيارة</h5>
                            <p class="small text-muted">يمكنك تحديث حالة الزيارة إلى مكتملة أو ملغية.</p>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button type="button" class="btn btn-success change-status-btn" data-status="completed">
                                    <i class="fas fa-check-circle me-1"></i> تعيين كزيارة مكتملة
                                </button>
                                <button type="button" class="btn btn-danger change-status-btn" data-status="cancelled">
                                    <i class="fas fa-times-circle me-1"></i> تعيين كزيارة ملغية
                                </button>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <h5 class="card-title">تعديل الزيارة</h5>
                            <p class="small text-muted">يمكنك تعديل تفاصيل الزيارة المخططة.</p>
                            
                            <div class="d-grid">
                                <a href="edit.php?id=<?php echo $visit_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit me-1"></i> تعديل الزيارة
                                </a>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> هذه الزيارة <?php echo $visit->visit_status === 'completed' ? 'مكتملة' : 'ملغية'; ?> ولا يمكن تعديلها.
                        </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> العودة إلى قائمة الزيارات
                        </a>
                        <?php if ($visit->visit_status === 'completed') : ?>
                            <a href="../add_sale.php?from_visit=<?php echo $visit_id; ?>" class="btn btn-info">
                                <i class="fas fa-dollar-sign me-1"></i> تحويل إلى مبيعة
                            </a>
                        <?php endif; ?>
                        <a href="index.php?action=delete&id=<?php echo $visit_id; ?>" class="btn btn-outline-danger btn-delete" onclick="return confirm('هل أنت متأكد من حذف هذه الزيارة؟');">
                            <i class="fas fa-trash me-1"></i> حذف الزيارة
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نموذج تغيير حالة الزيارة -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="change-status-form" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?id=' . $visit_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">تحديث حالة الزيارة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="status" id="status">
                    
                    <div class="mb-3" id="outcome-container">
                        <label for="outcome" class="form-label">نتيجة الزيارة</label>
                        <textarea class="form-control" id="outcome" name="outcome" rows="4" placeholder="أدخل نتيجة الزيارة وأهم النقاط التي تمت مناقشتها..."></textarea>
                    </div>
                    
                    <div class="alert alert-info" id="status-info">
                        <i class="fas fa-info-circle me-1"></i> <span id="status-message"></span>
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
    // تهيئة نموذج تغيير الحالة
    const changeStatusButtons = document.querySelectorAll('.change-status-btn');
    const changeStatusModal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
    const outcomeContainer = document.getElementById('outcome-container');
    const statusInfo = document.getElementById('status-info');
    const statusMessage = document.getElementById('status-message');
    
    changeStatusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            
            document.getElementById('status').value = status;
            
            if (status === 'completed') {
                outcomeContainer.style.display = 'block';
                statusInfo.className = 'alert alert-success';
                statusMessage.textContent = 'سيتم تعيين الزيارة كمكتملة. يرجى إدخال نتيجة الزيارة.';
            } else {
                outcomeContainer.style.display = 'none';
                statusInfo.className = 'alert alert-warning';
                statusMessage.textContent = 'سيتم تعيين الزيارة كملغية. هل أنت متأكد؟';
            }
            
            changeStatusModal.show();
        });
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>