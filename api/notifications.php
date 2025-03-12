<?php
/**
 * API للتعامل مع الإشعارات
 * يوفر واجهة برمجة تطبيقات للحصول على الإشعارات وتحديث حالتها
 */

// بدء جلسة للوصول لمعلومات المستخدم
session_start();

// التأكد من أن المستخدم قد قام بتسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    // إرسال رمز حالة الخطأ 401 (غير مصرح)
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'يجب تسجيل الدخول للوصول إلى هذه الخدمة'
    ]);
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Notification.php';

// تحديد رأس الاستجابة كـ JSON
header('Content-Type: application/json; charset=utf-8');

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة الإشعارات
$notification = new Notification($conn);

// الحصول على معرف المستخدم
$user_id = $_SESSION['user_id'];

// معالجة طلبات GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // تحديد ما إذا كان يجب إرجاع الإشعارات غير المقروءة فقط
    $unread_only = isset($_GET['unread']) && $_GET['unread'] == 1;
    
    // تحديد عدد الإشعارات المطلوبة (اختياري)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // الحصول على الإشعارات
    $notifications = $notification->getUserNotifications($user_id, $unread_only, $limit);
    
    // إرسال الإشعارات كـ JSON
    echo json_encode($notifications);
    exit;
}

// معالجة طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // قراءة البيانات من طلب POST
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    $response = [
        'status' => 'error',
        'message' => 'إجراء غير صالح'
    ];
    
    switch ($action) {
        // تعليم إشعار كمقروء
        case 'mark_as_read':
            if (isset($_POST['id'])) {
                $notification_id = (int)$_POST['id'];
                
                if ($notification->markAsRead($notification_id)) {
                    $response = [
                        'status' => 'success',
                        'message' => 'تم تعليم الإشعار كمقروء'
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'حدث خطأ أثناء تعليم الإشعار كمقروء'
                    ];
                }
            }
            break;
        
        // تعليم جميع الإشعارات كمقروءة
        case 'mark_all_read':
            if ($notification->markAllAsRead($user_id)) {
                $response = [
                    'status' => 'success',
                    'message' => 'تم تعليم جميع الإشعارات كمقروءة'
                ];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء تعليم جميع الإشعارات كمقروءة'
                ];
            }
            break;
        
        // حذف إشعار
        case 'delete':
            if (isset($_POST['id'])) {
                $notification_id = (int)$_POST['id'];
                
                if ($notification->delete($notification_id)) {
                    $response = [
                        'status' => 'success',
                        'message' => 'تم حذف الإشعار بنجاح'
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'حدث خطأ أثناء حذف الإشعار'
                    ];
                }
            }
            break;
        
        // حذف جميع الإشعارات
        case 'delete_all':
            if ($notification->deleteAll($user_id)) {
                $response = [
                    'status' => 'success',
                    'message' => 'تم حذف جميع الإشعارات بنجاح'
                ];
            } else {
                $response = [
                    'status' => 'error',
                    'message' => 'حدث خطأ أثناء حذف جميع الإشعارات'
                ];
            }
            break;
        
        // إضافة إشعار جديد (للاختبار أو للاستخدام الداخلي)
        case 'add':
            if (isset($_POST['title']) && isset($_POST['message']) && isset($_POST['type'])) {
                $title = $_POST['title'];
                $message = $_POST['message'];
                $type = $_POST['type'];
                $reference_id = isset($_POST['reference_id']) ? $_POST['reference_id'] : null;
                $reference_type = isset($_POST['reference_type']) ? $_POST['reference_type'] : null;
                
                if ($notification->send($user_id, $title, $message, $type, $reference_id, $reference_type)) {
                    $response = [
                        'status' => 'success',
                        'message' => 'تم إضافة الإشعار بنجاح'
                    ];
                } else {
                    $response = [
                        'status' => 'error',
                        'message' => 'حدث خطأ أثناء إضافة الإشعار'
                    ];
                }
            }
            break;
            
        // الحصول على عدد الإشعارات غير المقروءة
        case 'count_unread':
            $count = $notification->getUnreadCount($user_id);
            $response = [
                'status' => 'success',
                'count' => $count
            ];
            break;
    }
    
    // إرسال الاستجابة كـ JSON
    echo json_encode($response);
    exit;
}

// إذا وصلنا إلى هنا، فالطلب غير صالح
http_response_code(405); // طريقة غير مسموح بها
echo json_encode([
    'status' => 'error',
    'message' => 'طريقة الطلب غير مدعومة'
]);
?>