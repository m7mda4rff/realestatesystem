<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'classes/Notification.php';
require_once 'includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة الإشعارات
$notification = new Notification($conn);

// متغير لتخزين الرسائل
$message = '';

// معالجة العمليات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // تعليم جميع الإشعارات كمقروءة
    if (isset($_POST['mark_all_read'])) {
        if ($notification->markAllAsRead($_SESSION['user_id'])) {
            $message = createAlert('success', 'تم تعليم جميع الإشعارات كمقروءة');
        } else {
            $message = createAlert('danger', 'حدث خطأ أثناء تعليم الإشعارات كمقروءة');
        }
    }
    
    // حذف جميع الإشعارات
    if (isset($_POST['delete_all'])) {
        if ($notification->deleteAll($_SESSION['user_id'])) {
            $message = createAlert('success', 'تم حذف جميع الإشعارات بنجاح');
        } else {
            $message = createAlert('danger', 'حدث خطأ أثناء حذف الإشعارات');
        }
    }
    
    // تعليم إشعار واحد كمقروء
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        if ($notification->markAsRead($notification_id)) {
            $message = createAlert('success', 'تم تعليم الإشعار كمقروء');
        } else {
            $message = createAlert('danger', 'حدث خطأ أثناء تعليم الإشعار كمقروء');
        }
    }
    
    // حذف إشعار واحد
    if (isset($_POST['delete']) && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        if ($notification->delete($notification_id)) {
            $message = createAlert('success', 'تم حذف الإشعار بنجاح');
        } else {
            $message = createAlert('danger', 'حدث خطأ أثناء حذف الإشعار');
        }
    }
}

// الحصول على فلتر القراءة
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$unread_only = ($filter === 'unread');

// الحصول على إشعارات المستخدم
$notifications = $notification->getUserNotifications($_SESSION['user_id'], $unread_only);

// تعيين عنوان الصفحة
$page_title = 'الإشعارات';

// تضمين ملف رأس الصفحة
include_once 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">الإشعارات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">الإشعارات</li>
    </ol>
    
    <!-- عرض رسائل النجاح والخطأ -->
    <?php if (!empty($message)) : ?>
        <?php echo $message; ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-bell me-1"></i>
                    قائمة الإشعارات
                </div>
                <div>
                    <!-- فلتر الإشعارات -->
                    <div class="btn-group me-2">
                        <a href="notifications.php" class="btn btn-outline-primary btn-sm <?php echo ($filter === 'all') ? 'active' : ''; ?>">الكل</a>
                        <a href="notifications.php?filter=unread" class="btn btn-outline-primary btn-sm <?php echo ($filter === 'unread') ? 'active' : ''; ?>">غير المقروءة</a>
                    </div>
                    
                    <!-- زر تعليم الكل كمقروء -->
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-success btn-sm" <?php echo empty($notifications) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check-double me-1"></i> تعليم الكل كمقروء
                        </button>
                    </form>
                    
                    <!-- زر حذف الكل -->
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="d-inline">
                        <button type="submit" name="delete_all" class="btn btn-danger btn-sm" <?php echo empty($notifications) ? 'disabled' : ''; ?> onclick="return confirm('هل أنت متأكد من حذف جميع الإشعارات؟');">
                            <i class="fas fa-trash me-1"></i> حذف الكل
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($notifications) > 0) : ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification_item) : ?>
                        <div class="list-group-item list-group-item-action <?php echo ($notification_item['is_read'] == 0) ? 'bg-light' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">
                                    <?php if ($notification_item['is_read'] == 0) : ?>
                                        <span class="badge bg-primary">جديد</span>
                                    <?php endif; ?>
                                    <?php echo getNotificationIcon($notification_item['type']); ?>
                                    <?php echo htmlspecialchars($notification_item['title']); ?>
                                </h5>
                                <small class="text-muted"><?php echo formatDate($notification_item['created_at'], 'Y-m-d H:i'); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($notification_item['message']); ?></p>
                            <div class="d-flex mt-2">
                                <!-- رابط للعنصر المرجعي (إن وجد) -->
                                <?php if (!empty($notification_item['reference_id']) && !empty($notification_item['reference_type'])) : ?>
                                    <a href="<?php echo getNotificationLink($notification_item['reference_type'], $notification_item['reference_id']); ?>" class="btn btn-primary btn-sm me-2">
                                        <i class="fas fa-eye me-1"></i> عرض
                                    </a>
                                <?php endif; ?>
                                
                                <!-- تعليم كمقروء -->
                                <?php if ($notification_item['is_read'] == 0) : ?>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="me-2">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification_item['id']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i> تعليم كمقروء
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <!-- حذف الإشعار -->
                                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification_item['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا الإشعار؟');">
                                        <i class="fas fa-trash me-1"></i> حذف
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد إشعارات متاحة<?php echo ($filter === 'unread') ? ' غير مقروءة' : ''; ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/**
 * دالة للحصول على أيقونة الإشعار بناءً على نوعه
 * 
 * @param string $type نوع الإشعار
 * @return string أيقونة Font Awesome
 */
function getNotificationIcon($type) {
    switch ($type) {
        case 'sale':
            return '<i class="fas fa-shopping-cart text-success me-1"></i>';
        case 'commission':
            return '<i class="fas fa-coins text-warning me-1"></i>';
        case 'target':
            return '<i class="fas fa-bullseye text-primary me-1"></i>';
        case 'visit':
            return '<i class="fas fa-calendar-check text-info me-1"></i>';
        default:
            return '<i class="fas fa-bell text-secondary me-1"></i>';
    }
}

/**
 * دالة للحصول على رابط العنصر المرجعي
 * 
 * @param string $type نوع العنصر المرجعي
 * @param int $id معرف العنصر المرجعي
 * @return string الرابط
 */
function getNotificationLink($type, $id) {
    $base_url = URL_ROOT;
    $role = $_SESSION['role'];
    $prefix = ($role === 'admin') ? 'admin' : (($role === 'manager') ? 'manager' : 'sales');
    
    switch ($type) {
        case 'sale':
            return "{$base_url}/{$prefix}/sales/view.php?id={$id}";
        case 'commission':
            return "{$base_url}/{$prefix}/commissions/view.php?id={$id}";
        case 'target':
            return "{$base_url}/{$prefix}/targets/view.php?id={$id}";
        case 'visit':
            return "{$base_url}/{$prefix}/visits/view.php?id={$id}";
        default:
            return "#";
    }
}

// تضمين ملف تذييل الصفحة
include_once 'includes/footer.php';
?>