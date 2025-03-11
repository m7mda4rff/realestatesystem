<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
require_once '../../classes/Commission.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف العمولة وحالة الدفع
if (!isset($_POST['commission_id']) || !isset($_POST['status'])) {
    $_SESSION['error_message'] = 'البيانات المطلوبة غير مكتملة';
    header('Location: index.php');
    exit;
}

// الحصول على المعرف وحالة الدفع
$commission_id = (int)$_POST['commission_id'];
$status = trim($_POST['status']);
$payment_notes = isset($_POST['payment_notes']) ? trim($_POST['payment_notes']) : '';

// التحقق من صحة حالة الدفع
$valid_statuses = ['paid', 'pending'];
if (!in_array($status, $valid_statuses)) {
    $_SESSION['error_message'] = 'حالة الدفع غير صالحة';
    header('Location: view.php?id=' . $commission_id);
    exit;
}

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة العمولة
$commission = new Commission($conn);

// قراءة العمولة
if (!$commission->readOne($commission_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على العمولة المطلوبة';
    header('Location: index.php');
    exit;
}

// إذا كانت العمولة بالفعل في الحالة المطلوبة
if ($commission->status === $status) {
    $_SESSION['error_message'] = 'العمولة بالفعل في الحالة المطلوبة';
    header('Location: view.php?id=' . $commission_id);
    exit;
}

// تحديث حالة العمولة
$commission->status = $status;
$commission->notes = $payment_notes;

if ($commission->updateStatus()) {
    if ($status === 'paid') {
        $_SESSION['success_message'] = 'تم تسجيل دفع العمولة بنجاح';
    } else {
        $_SESSION['success_message'] = 'تم تحديث حالة العمولة بنجاح';
    }
} else {
    $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث حالة العمولة';
}

// إعادة التوجيه إلى صفحة عرض العمولة
header('Location: view.php?id=' . $commission_id);
exit;
?>