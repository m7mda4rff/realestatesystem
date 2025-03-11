<?php
/**
 * ملف الثوابت
 * يحتوي على كافة ثوابت النظام
 */

// ثوابت المسارات
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('URL_ROOT', 'http://' . $_SERVER['HTTP_HOST'] . '/real_estate_system');
define('ASSETS_URL', URL_ROOT . '/assets');

// ثوابت عامة للنظام
define('SYSTEM_NAME', 'نظام إدارة التسويق العقاري');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_EMAIL', 'admin@example.com');

// ثوابت أدوار المستخدمين
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_SALESPERSON', 'salesperson');

// ثوابت حالات التحصيل
define('PAYMENT_PAID', 'paid');
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_CANCELLED', 'cancelled');

// ثوابت حالات الزيارات
define('VISIT_PLANNED', 'planned');
define('VISIT_COMPLETED', 'completed');
define('VISIT_CANCELLED', 'cancelled');

// ثوابت العمولات
define('COMMISSION_DEFAULT_RATE', 2.5); // 2.5%
define('COMMISSION_PENDING', 'pending');
define('COMMISSION_PAID', 'paid');

// ثوابت التنبيهات
define('NOTIFICATION_SALE', 'sale');
define('NOTIFICATION_COMMISSION', 'commission');
define('NOTIFICATION_TARGET', 'target');
define('NOTIFICATION_VISIT', 'visit');
?>