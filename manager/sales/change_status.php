<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Sale.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);

// الحصول على معرف المدير من الجلسة
$manager_id = $_SESSION['user_id'];

// التحقق من أن النموذج تم إرساله
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['sale_id']) || !isset($_POST['new_status'])) {
    $_SESSION['error_message'] = 'طلب غير صالح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المبيعة والحالة الجديدة
$sale_id = (int)$_POST['sale_id'];
$new_status = $_POST['new_status'];

// التحقق من صحة الحالة
if (!in_array($new_status, ['paid', 'pending', 'cancelled'])) {
    $_SESSION['error_message'] = 'حالة غير صالحة';
    header('Location: index.php');
    exit;
}

// الحصول على معلومات المبيعة
if (!$sale->readOne($sale_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المبيعة';
    header('Location: index.php');
    exit;
}

// الحصول على قائمة المندوبين التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);
$salespeople_ids = array_column($salespeople, 'id');

// التحقق من أن المبيعة تخص أحد مندوبي المدير
if (!in_array($sale->salesperson_id, $salespeople_ids)) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية تعديل هذه المبيعة';
    header('Location: index.php');
    exit;
}

// تغيير حالة المبيعة
if ($sale->changeStatus($sale_id, $new_status)) {
    $_SESSION['success_message'] = 'تم تحديث حالة المبيعة بنجاح';
} else {
    $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة المبيعة';
}

// إعادة التوجيه إلى الصفحة السابقة
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'view.php') !== false) {
    // إذا كان المستخدم قادماً من صفحة التفاصيل، أعده إليها
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    // وإلا أعده إلى قائمة المبيعات
    header('Location: index.php');
}
exit;
?>