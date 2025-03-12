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

// التحقق من وجود معرف المستخدم في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المستخدم غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المستخدم
$user_id = (int)$_GET['id'];

// التحقق من عدم حذف المستخدم الحالي أو حساب المسؤول الرئيسي
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error_message'] = 'لا يمكن حذف المستخدم الحالي';
    header('Location: index.php');
    exit;
}

if ($user_id === 1) {
    $_SESSION['error_message'] = 'لا يمكن حذف حساب المسؤول الرئيسي';
    header('Location: index.php');
    exit;
}

// محاولة قراءة تفاصيل المستخدم
$user->readOne($user_id);
$user_name = $user->full_name; // احتفظ باسم المستخدم للرسالة

// تعيين المعرف للحذف
$user->id = $user_id;

// محاولة حذف المستخدم
if ($user->delete()) {
    $_SESSION['success_message'] = 'تم حذف المستخدم "' . $user_name . '" بنجاح';
    header('Location: index.php');
    exit;
} else {
    // التحقق من وجود مبيعات أو سجلات مرتبطة بالمستخدم
    $_SESSION['error_message'] = 'لا يمكن حذف المستخدم. قد يكون لديه مبيعات أو سجلات مرتبطة في النظام';
    header('Location: view.php?id=' . $user_id);
    exit;
}
?>