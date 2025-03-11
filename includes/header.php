<?php
// التأكد من بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملف ثوابت النظام
require_once $_SERVER['DOCUMENT_ROOT'] . '/real_estate_system/config/constants.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . URL_ROOT . '/auth/login.php');
    exit;
}

// الحصول على عدد الإشعارات غير المقروءة
$notification_count = 0;

if (isset($_SESSION['user_id'])) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/real_estate_system/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/real_estate_system/classes/Notification.php';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $notification = new Notification($conn);
    $notification_count = $notification->getUnreadCount($_SESSION['user_id']);
}

// تحديد العنوان المخصص للصفحة أو استخدام اسم النظام الافتراضي
$page_title = isset($page_title) ? $page_title . ' - ' . SYSTEM_NAME : SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $page_title; ?></title>
<!-- تضمين ملف CSS العصري الجديد -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style-modern.css">
    <!-- تضمين Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    
    <!-- تضمين Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <!-- تضمين Bootstrap RTL -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">

<!-- تضمين تعديلات الدروب داون -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/form-select-fix.css">
    <!-- تضمين Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    
    <!-- تضمين DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    
    <!-- تضمين DateRangePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    
    <!-- تضمين ملف CSS المخصص -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style-modern.css">
    
    <!-- أي ملفات CSS إضافية مخصصة للصفحة -->
    <?php if (isset($page_specific_css)) { echo $page_specific_css; } ?>
</head>
<body class="sb-nav-fixed">
    <!-- شريط التنقل العلوي -->
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <!-- شعار النظام -->
        <a class="navbar-brand ps-3" href="<?php echo URL_ROOT; ?>/index.php"><?php echo SYSTEM_NAME; ?></a>
        
        <!-- زر تبديل الشريط الجانبي -->
        <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- شريط البحث -->
        <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
            <div class="input-group">
                <input class="form-control" type="text" placeholder="بحث..." aria-label="بحث..." aria-describedby="btnNavbarSearch" />
                <button class="btn btn-primary" id="btnNavbarSearch" type="button"><i class="fas fa-search"></i></button>
            </div>
        </form>
        
        <!-- قائمة المستخدم -->
        <ul class="navbar-nav ms-0 ms-md-0 me-3 me-lg-4">
            <!-- الإشعارات -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdownNotifications" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="badge bg-danger"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownNotifications">
                    <li><h6 class="dropdown-header">الإشعارات</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <!-- هنا يتم إضافة الإشعارات بشكل ديناميكي من خلال Ajax -->
                    <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/notifications.php">عرض جميع الإشعارات</a></li>
                </ul>
            </li>
            
            <!-- قائمة المستخدم -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user fa-fw"></i> <?php echo $_SESSION['full_name']; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/auth/profile.php"><i class="fas fa-user-cog me-1"></i> الملف الشخصي</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo URL_ROOT; ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-1"></i> تسجيل الخروج</a></li>
                </ul>
            </li>
        </ul>
    </nav>
    
    <div id="layoutSidenav">
        <!-- الشريط الجانبي -->
        <?php include_once 'sidebar.php'; ?>
        
        <div id="layoutSidenav_content">
            <main>
                <!-- محتوى الصفحة الرئيسي يبدأ هنا -->