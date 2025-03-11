<?php
// بدء جلسة
session_start();

// إزالة جميع متغيرات الجلسة
$_SESSION = array();

// إذا كانت الجلسة تستخدم ملف تعريف الارتباط، فقم بتدميره
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// تضمين ملف ثوابت النظام
require_once '../config/constants.php';

// إعادة توجيه إلى صفحة تسجيل الدخول
header('Location: login.php');
exit;
?>