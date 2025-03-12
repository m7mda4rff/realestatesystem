<?php
/**
 * فئة الأهداف
 * تتعامل مع كافة العمليات المتعلقة بأهداف المبيعات في النظام
 */
class Target {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'targets';
    
    // خصائص الهدف
    public $id;
    public $salesperson_id;
    public $target_amount;
    public $achieved_amount;
    public $start_date;
    public $end_date;
    public $notes;
    public $created_by;
    public $created_at;
    public $updated_at;
    
    /**
     * دالة البناء للاتصال بقاعدة البيانات
     * 
     * @param object $db كائن الاتصال بقاعدة البيانات
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * إنشاء هدف جديد
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (salesperson_id, target_amount, achieved_amount, start_date, end_date, notes, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        $this->target_amount = htmlspecialchars(strip_tags($this->target_amount));
        $this->achieved_amount = htmlspecialchars(strip_tags($this->achieved_amount));
        $this->start_date = htmlspecialchars(strip_tags($this->start_date));
        $this->end_date = htmlspecialchars(strip_tags($this->end_date));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->created_by = htmlspecialchars(strip_tags($this->created_by));
        
        // ربط المعلمات
        $stmt->bind_param("iddssis", 
            $this->salesperson_id, 
            $this->target_amount, 
            $this->achieved_amount, 
            $this->start_date,
            $this->end_date,
            $this->notes,
            $this->created_by
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث معلومات هدف
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function update() {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET target_amount = ?,
                      start_date = ?,
                      end_date = ?,
                      notes = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->target_amount = htmlspecialchars(strip_tags($this->target_amount));
        $this->start_date = htmlspecialchars(strip_tags($this->start_date));
        $this->end_date = htmlspecialchars(strip_tags($this->end_date));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // ربط المعلمات
        $stmt->bind_param("dsssi", 
            $this->target_amount, 
            $this->start_date, 
            $this->end_date, 
            $this->notes,
            $this->id
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث المبلغ المحقق
     * 
     * @param int $id معرف الهدف
     * @param float $amount المبلغ المضاف
     * @return boolean نجاح أو فشل العملية
     */
    public function updateAchievedAmount($id, $amount) {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET achieved_amount = achieved_amount + ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("di", $amount, $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف هدف
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function delete() {
        // استعلام
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $this->id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على معلومات هدف محدد
     * 
     * @param int $id معرف الهدف
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT t.*, u.full_name as salesperson_name, c.full_name as created_by_name
                  FROM " . $this->table . " t
                  LEFT JOIN users u ON t.salesperson_id = u.id
                  LEFT JOIN users c ON t.created_by = c.id
                  WHERE t.id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على السجل
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // تعيين الخصائص
            $this->id = $row['id'];
            $this->salesperson_id = $row['salesperson_id'];
            $this->target_amount = $row['target_amount'];
            $this->achieved_amount = $row['achieved_amount'];
            $this->start_date = $row['start_date'];
            $this->end_date = $row['end_date'];
            $this->notes = $row['notes'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على الهدف الحالي لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @return array بيانات الهدف
     */
    public function getCurrentTarget($salesperson_id) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . "
                  WHERE salesperson_id = ? 
                  AND CURRENT_DATE() BETWEEN start_date AND end_date";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على السجل
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        // إذا لم يوجد هدف حالي، أرجع بيانات فارغة
        return [
            'target_amount' => 0,
            'achieved_amount' => 0,
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t')
        ];
    }
    
    /**
     * الحصول على أهداف مندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param int $limit عدد السجلات (اختياري)
     * @return array مصفوفة من الأهداف
     */
    public function getTargetsBySalesperson($salesperson_id, $limit = null) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  ORDER BY end_date DESC";
        
        // إضافة حد للنتائج
        if ($limit) {
            $query .= " LIMIT ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($limit) {
            $stmt->bind_param("ii", $salesperson_id, $limit);
        } else {
            $stmt->bind_param("i", $salesperson_id);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $targets = [];
        while ($row = $result->fetch_assoc()) {
            $targets[] = $row;
        }
        
        return $targets;
    }
    
    /**
     * التحقق من تداخل الفترات لأهداف المندوب
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $start_date تاريخ البداية
     * @param string $end_date تاريخ النهاية
     * @param int $exclude_id معرف الهدف المستثنى (اختياري)
     * @return boolean نتيجة التحقق
     */
    public function checkDateOverlap($salesperson_id, $start_date, $end_date, $exclude_id = null) {
        // استعلام أبسط للتحقق من التداخل
        $query = "SELECT COUNT(*) as count FROM " . $this->table . "
                  WHERE salesperson_id = ? 
                  AND NOT (end_date < ? OR start_date > ?)";
        
        // إضافة شرط الاستثناء
        if ($exclude_id) {
            $query .= " AND id != ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($exclude_id) {
            $stmt->bind_param("issi", $salesperson_id, $start_date, $end_date, $exclude_id);
        } else {
            $stmt->bind_param("iss", $salesperson_id, $start_date, $end_date);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }
}
?>