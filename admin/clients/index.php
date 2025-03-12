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
require_once '../../classes/Client.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة العميل
$client = new Client($conn);

// التحقق من عمليات الحذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $client_id = (int)$_GET['id'];
    
    // التحقق من وجود العميل وحذفه
    $client->id = $client_id;
    if ($client->delete()) {
        $_SESSION['success_message'] = 'تم حذف العميل بنجاح';
    } else {
        $_SESSION['error_message'] = 'حدث خطأ أثناء حذف العميل. قد يكون هناك مبيعات مرتبطة بهذا العميل.';
    }
    
    // إعادة توجيه لتجنب إعادة تنفيذ العملية عند إعادة تحميل الصفحة
    header('Location: index.php');
    exit;
}

// إعدادات البحث والفلترة
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// الحصول على قائمة العملاء
$clients = $client->readAll($search_term);

// تعيين عنوان الصفحة
$page_title = 'إدارة العملاء';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة العملاء</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة العملاء</li>
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
    
    <!-- بطاقة البحث والإضافة -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-search me-1"></i>
            بحث وإضافة
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="mb-0">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="ابحث عن عميل..." value="<?php echo htmlspecialchars($search_term); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> بحث
                            </button>
                            <?php if (!empty($search_term)) : ?>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> إلغاء البحث
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <a href="add.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> إضافة عميل جديد
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة العملاء -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            قائمة العملاء
        </div>
        <div class="card-body">
            <?php if (count($clients) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>اسم العميل</th>
                                <th>رقم الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th>العنوان</th>
                                <th>عدد المبيعات</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $index => $client_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($client_item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($client_item['phone'] ?: 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($client_item['email'] ?: 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($client_item['address'] ?: 'غير محدد'); ?></td>
                                    <td>
                                        <?php
                                            // الحصول على عدد المبيعات للعميل
                                            $sales_count = $client->getSalesCount($client_item['id']);
                                            echo $sales_count;
                                        ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $client_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $client_item['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="btn btn-danger btn-sm btn-delete" title="حذف" data-id="<?php echo $client_item['id']; ?>" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا يوجد عملاء متاحين.
                    <?php if (!empty($search_term)) : ?>
                        لا توجد نتائج مطابقة لـ "<?php echo htmlspecialchars($search_term); ?>".
                        <a href="index.php">العودة إلى قائمة العملاء</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
                هل أنت متأكد من رغبتك في حذف هذا العميل؟
                <p class="text-danger mt-2"><strong>تنبيه:</strong> سيتم حذف جميع بيانات العميل بشكل نهائي!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">نعم، حذف</a>
            </div>
        </div>
    </div>
</div>

<script>
// إعداد رابط الحذف في المودال
document.addEventListener('DOMContentLoaded', function() {
    // تعيين رابط الحذف عند النقر على زر الحذف
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.getAttribute('data-id');
            document.getElementById('confirmDelete').href = 'index.php?action=delete&id=' + clientId;
        });
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>