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

// التحقق من عمليات الحذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $visit_id = (int)$_GET['id'];
    
    // التحقق من وجود الزيارة وحذفها
    $visit->id = $visit_id;
    $visit->salesperson_id = $_SESSION['user_id']; // للتأكد من أن الزيارة تخص المندوب الحالي
    
    if ($visit->delete()) {
        $success_message = 'تم حذف الزيارة بنجاح';
    } else {
        $error_message = 'حدث خطأ أثناء حذف الزيارة';
    }
}

// معالجة تغيير حالة الزيارة
if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $visit_id = (int)$_POST['visit_id'];
    $status = $_POST['status'];
    $outcome = isset($_POST['outcome']) ? $_POST['outcome'] : '';
    
    if ($visit->updateStatus($visit_id, $status, $outcome)) {
        $success_message = 'تم تحديث حالة الزيارة بنجاح';
    } else {
        $error_message = 'حدث خطأ أثناء تحديث حالة الزيارة';
    }
}

// إعدادات الفلترة
$filters = [];
$filters['salesperson_id'] = $_SESSION['user_id'];

// فلترة حسب الحالة
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['visit_status'] = $_GET['status'];
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

// الحصول على قائمة الزيارات
$visits = $visit->readAll($filters);

// الحصول على إحصائيات الزيارات
$visit_stats = $visit->getVisitStats($_SESSION['user_id'], 'month');

// تعيين عنوان الصفحة
$page_title = 'إدارة الزيارات الخارجية';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة الزيارات الخارجية</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة الزيارات</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($success_message)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <!-- ملخص الزيارات -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $visit_stats['total']; ?></h4>
                            <div class="small">إجمالي الزيارات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar-check fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $visit_stats['planned']; ?></h4>
                            <div class="small">الزيارات المخططة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=planned">عرض المخططة</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $visit_stats['completed']; ?></h4>
                            <div class="small">الزيارات المكتملة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=completed">عرض المكتملة</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $visit_stats['cancelled']; ?></h4>
                            <div class="small">الزيارات الملغية</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-times-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=cancelled">عرض الملغية</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة الزيارات
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">حالة الزيارة</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">الكل</option>
                            <option value="planned" <?php echo (isset($_GET['status']) && $_GET['status'] === 'planned') ? 'selected' : ''; ?>>مخططة</option>
                            <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="search" class="form-label">بحث</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="اسم الشركة، العميل...">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> بحث
                        </button>
                        <a href="index.php" class="btn btn-secondary me-2">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                        <a href="add.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> إضافة زيارة جديدة
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- بطاقة الزيارات -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-calendar-check me-1"></i>
                    قائمة الزيارات
                </div>
                <div>
                    <a href="calendar.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar-alt me-1"></i> عرض التقويم
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($visits) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>الشركة</th>
                                <th>العميل</th>
                                <th>الهاتف</th>
                                <th>تاريخ الزيارة</th>
                                <th>الغرض</th>
                                <th>الحالة</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits as $index => $visit_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($visit_item['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($visit_item['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($visit_item['client_phone']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($visit_item['visit_time'])); ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($visit_item['purpose'], 0, 30)) . (mb_strlen($visit_item['purpose']) > 30 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($visit_item['visit_status'] === 'planned') : ?>
                                            <span class="badge visit-planned">مخططة</span>
                                        <?php elseif ($visit_item['visit_status'] === 'completed') : ?>
                                            <span class="badge visit-completed">مكتملة</span>
                                        <?php else : ?>
                                            <span class="badge visit-cancelled">ملغية</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $visit_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($visit_item['visit_status'] === 'planned') : ?>
                                                <a href="edit.php?id=<?php echo $visit_item['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-success btn-sm change-status-btn" data-id="<?php echo $visit_item['id']; ?>" data-status="completed" title="تعيين كمكتملة">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm change-status-btn" data-id="<?php echo $visit_item['id']; ?>" data-status="cancelled" title="تعيين كملغية">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php else : ?>
                                                <a href="edit.php?id=<?php echo $visit_item['id']; ?>" class="btn btn-secondary btn-sm disabled" title="لا يمكن التعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-secondary btn-sm disabled" title="لا يمكن تغيير الحالة">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-sm disabled" title="لا يمكن تغيير الحالة">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="index.php?action=delete&id=<?php echo $visit_item['id']; ?>" class="btn btn-danger btn-sm btn-delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذه الزيارة؟');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد زيارات متاحة.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- نموذج تغيير حالة الزيارة -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="change-status-form" method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeStatusModalLabel">تحديث حالة الزيارة</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="visit_id" id="visit_id">
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
            const visitId = this.getAttribute('data-id');
            const status = this.getAttribute('data-status');
            
            document.getElementById('visit_id').value = visitId;
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