<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header('Location: ../../auth/login.php');
    exit;
}

// التحقق من استدعاء الصفحة بطريقة POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'طريقة غير صالحة للوصول إلى الصفحة';
    header('Location: index.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المبيعة وحالة الدفع
if (!isset($_POST['sale_id']) || !isset($_POST['payment_status'])) {
    $_SESSION['error_message'] = 'البيانات المطلوبة غير مكتملة';
    header('Location: index.php');
    exit;
}

// الحصول على المعرف وحالة الدفع
$sale_id = (int)$_POST['sale_id'];
$payment_status = trim($_POST['payment_status']);

// التحقق من صحة حالة الدفع
$valid_statuses = ['paid', 'pending', 'cancelled'];
if (!in_array($payment_status, $valid_statuses)) {
    $_SESSION['error_message'] = 'حالة الدفع غير صالحة';
    header('Location: view.php?id=' . $sale_id);
    exit;
}

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المبيعة
$sale = new Sale($conn);

// تحديث حالة المبيعة
if ($sale->changeStatus($sale_id, $payment_status)) {
    // إذا تم تحويل الحالة إلى "مدفوعة"، يمكن إضافة منطق إضافي هنا مثل تحديث العمولات

    // تعيين رسالة النجاح
    $_SESSION['success_message'] = 'تم تحديث حالة المبيعة بنجاح إلى "' . translateSaleStatus($payment_status) . '"';
} else {
    $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة المبيعة';
}

// إعادة التوجيه إلى صفحة عرض المبيعة
header('Location: view.php?id=' . $sale_id);
exit;
?>