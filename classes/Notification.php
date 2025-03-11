<?php
/**
 * فئة الإشعارات
 * تتعامل مع كافة العمليات المتعلقة بإشعارات المستخدمين في النظام
 */
class Notification {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'notifications';
    
    // خصائص الإشعار
    public $id;
    public $user_id;
    public $title;
    public $message;
    public $is_read;
    public $type;
    public $reference_id;
    public $reference_type;
    public $created_at;
    
    /**
     * دالة البناء للاتصال بقاعدة البيانات
     * 
     * @param object $db كائن الاتصال بقاعدة البيانات
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * إنشاء إشعار جديد
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (user_id, title, message, is_read, type, reference_id, reference_type)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->message = htmlspecialchars(strip_tags($this->message));
        $this->is_read = htmlspecialchars(strip_tags($this->is_read));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->reference_id = htmlspecialchars(strip_tags($this->reference_id));
        $this->reference_type = htmlspecialchars(strip_tags($this->reference_type));
        
        // ربط المعلمات
        $stmt->bind_param("ississs", 
            $this->user_id, 
            $this->title, 
            $this->message, 
            $this->is_read,
            $this->type,
            $this->reference_id,
            $this->reference_type
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        
        return false;
    }
    
    /**
     * تعيين الإشعار كمقروء
     * 
     * @param int $id معرف الإشعار
     * @return boolean نجاح أو فشل العملية
     */
    public function markAsRead($id) {
        // استعلام
        $query = "UPDATE " . $this->table . " SET is_read = 1 WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * تعيين جميع إشعارات المستخدم كمقروءة
     * 
     * @param int $user_id معرف المستخدم
     * @return boolean نجاح أو فشل العملية
     */
    public function markAllAsRead($user_id) {
        // استعلام
        $query = "UPDATE " . $this->table . " SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $user_id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف إشعار
     * 
     * @param int $id معرف الإشعار
     * @return boolean نجاح أو فشل العملية
     */
    public function delete($id) {
        // استعلام
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف جميع إشعارات المستخدم
     * 
     * @param int $user_id معرف المستخدم
     * @return boolean نجاح أو فشل العملية
     */
    public function deleteAll($user_id) {
        // استعلام
        $query = "DELETE FROM " . $this->table . " WHERE user_id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $user_id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على إشعارات المستخدم
     * 
     * @param int $user_id معرف المستخدم
     * @param boolean $unread_only إرجاع الإشعارات غير المقروءة فقط
     * @param int $limit عدد الإشعارات (اختياري)
     * @return array مصفوفة من الإشعارات
     */
    public function getUserNotifications($user_id, $unread_only = false, $limit = null) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = ?";
        
        // إضافة شرط الإشعارات غير المقروءة
        if ($unread_only) {
            $query .= " AND is_read = 0";
        }
        
        // ترتيب النتائج
        $query .= " ORDER BY created_at DESC";
        
        // إضافة حد للنتائج
        if ($limit) {
            $query .= " LIMIT ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($limit) {
            $stmt->bind_param("ii", $user_id, $limit);
        } else {
            $stmt->bind_param("i", $user_id);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * الحصول على عدد الإشعارات غير المقروءة للمستخدم
     * 
     * @param int $user_id معرف المستخدم
     * @return int عدد الإشعارات غير المقروءة
     */
    public function getUnreadCount($user_id) {
        // استعلام
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE user_id = ? AND is_read = 0";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $user_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'];
    }
    
    /**
     * إرسال إشعار جديد
     * 
     * @param int $user_id معرف المستخدم
     * @param string $title عنوان الإشعار
     * @param string $message نص الإشعار
     * @param string $type نوع الإشعار
     * @param int $reference_id معرف العنصر المرجعي (اختياري)
     * @param string $reference_type نوع العنصر المرجعي (اختياري)
     * @return boolean نجاح أو فشل العملية
     */
    public function send($user_id, $title, $message, $type, $reference_id = null, $reference_type = null) {
        $this->user_id = $user_id;
        $this->title = $title;
        $this->message = $message;
        $this->is_read = 0;
        $this->type = $type;
        $this->reference_id = $reference_id;
        $this->reference_type = $reference_type;
        
        return $this->create();
    }
}
?>