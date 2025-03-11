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
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المستخدم
$user = new User($conn);

// التحقق من عمليات الحذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // التحقق من عدم حذف المستخدم الحالي أو حساب المسؤول الرئيسي
    if ($user_id !== $_SESSION['user_id'] && $user_id !== 1) {
        $user->id = $user_id;
        if ($user->delete()) {
            $success_message = 'تم حذف المستخدم بنجاح';
        } else {
            $error_message = 'حدث خطأ أثناء حذف المستخدم';
        }
    } else {
        $error_message = 'لا يمكن حذف المستخدم الحالي أو حساب المسؤول الرئيسي';
    }
}

// فلترة المستخدمين حسب الدور إذا تم تحديده
$role_filter = isset($_GET['role']) ? $_GET['role'] : null;
$filters = [];
if ($role_filter) {
    $filters['role'] = $role_filter;
}

// الحصول على قائمة المستخدمين
$users = $user->readAll($filters);

// تعيين عنوان الصفحة
$page_title = 'إدارة المستخدمين';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة المستخدمين</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة المستخدمين</li>
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
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-users me-1"></i>
                    قائمة المستخدمين
                </div>
                <div>
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> إضافة مستخدم
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- فلاتر البحث -->
            <div class="mb-3">
                <div class="btn-group" role="group" aria-label="فلترة المستخدمين">
                    <a href="index.php" class="btn btn-outline-primary <?php echo !$role_filter ? 'active' : ''; ?>">
                        الجميع
                    </a>
                    <a href="index.php?role=admin" class="btn btn-outline-primary <?php echo $role_filter === 'admin' ? 'active' : ''; ?>">
                        المسؤولون
                    </a>
                    <a href="index.php?role=manager" class="btn btn-outline-primary <?php echo $role_filter === 'manager' ? 'active' : ''; ?>">
                        المديرون
                    </a>
                    <a href="index.php?role=salesperson" class="btn btn-outline-primary <?php echo $role_filter === 'salesperson' ? 'active' : ''; ?>">
                        مندوبو المبيعات
                    </a>
                </div>
            </div>
            
            <?php if (count($users) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>الاسم الكامل</th>
                                <th>اسم المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>الهاتف</th>
                                <th>الدور</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($user_item['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user_item['phone'] ?? 'غير محدد'); ?></td>
                                    <td>
                                        <?php
                                            switch ($user_item['role']) {
                                                case 'admin':
                                                    echo '<span class="badge bg-danger">مسؤول النظام</span>';
                                                    break;
                                                case 'manager':
                                                    echo '<span class="badge bg-warning">مدير مبيعات</span>';
                                                    break;
                                                case 'salesperson':
                                                    echo '<span class="badge bg-info">مندوب مبيعات</span>';
                                                    break;
                                                default:
                                                    echo $user_item['role'];
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $user_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user_item['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user_item['id'] !== $_SESSION['user_id'] && $user_item['id'] !== 1) : ?>
                                            <a href="index.php?action=delete&id=<?php echo $user_item['id']; ?>" class="btn btn-danger btn-sm btn-delete" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else : ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="لا يمكن حذف هذا المستخدم">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا يوجد مستخدمين متاحين.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>