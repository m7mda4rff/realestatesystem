<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

// توجيه المستخدم إلى لوحة التحكم المناسبة بناءً على الدور
if ($_SESSION['role'] === 'admin') {
    header('Location: admin/index.php');
} elseif ($_SESSION['role'] === 'manager') {
    header('Location: manager/index.php');
} else {
    header('Location: sales/index.php');
}
exit;
?>