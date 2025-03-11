<?php
/**
 * فئة العمولات
 * تتعامل مع كافة العمليات المتعلقة بعمولات مندوبي المبيعات
 */
class Commission {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'commissions';
    
    // خصائص العمولة
    public $id;
    public $sale_id;
    public $salesperson_id;
    public $amount;
    public $status;
    public $payment_date;
    public $notes;
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
     * إنشاء عمولة جديدة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (sale_id, salesperson_id, amount, status, notes)
                  VALUES (?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->sale_id = htmlspecialchars(strip_tags($this->sale_id));
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        
        // ربط المعلمات
        $stmt->bind_param("iidss", 
            $this->sale_id, 
            $this->salesperson_id, 
            $this->amount, 
            $this->status,
            $this->notes
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث حالة العمولة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function updateStatus() {
        // الاستعلام - إذا كانت الحالة مدفوعة، قم بتعيين تاريخ الدفع
        if ($this->status === 'paid') {
            $query = "UPDATE " . $this->table . "
                      SET status = ?, payment_date = CURRENT_DATE(), notes = ?
                      WHERE id = ?";
        } else {
            $query = "UPDATE " . $this->table . "
                      SET status = ?, payment_date = NULL, notes = ?
                      WHERE id = ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // ربط المعلمات
        $stmt->bind_param("ssi", 
            $this->status, 
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
     * تحديث عمولات متعددة
     * 
     * @param array $commission_ids مصفوفة معرفات العمولات
     * @param string $status الحالة الجديدة
     * @return boolean نجاح أو فشل العملية
     */
    public function updateMultiple($commission_ids, $status) {
        // التحقق من وجود عمولات للتحديث
        if (empty($commission_ids)) {
            return false;
        }
        
        // إنشاء علامات استفهام للمعلمات
        $placeholders = implode(',', array_fill(0, count($commission_ids), '?'));
        
        // الاستعلام - إذا كانت الحالة مدفوعة، قم بتعيين تاريخ الدفع
        if ($status === 'paid') {
            $query = "UPDATE " . $this->table . "
                      SET status = ?, payment_date = CURRENT_DATE()
                      WHERE id IN ($placeholders)";
        } else {
            $query = "UPDATE " . $this->table . "
                      SET status = ?, payment_date = NULL
                      WHERE id IN ($placeholders)";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // إنشاء مصفوفة المعلمات
        $params = array_merge([$status], $commission_ids);
        
        // تحديد نوع المعلمات
        $types = 's' . str_repeat('i', count($commission_ids));
        
        // ربط المعلمات
        $stmt->bind_param($types, ...$params);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف عمولة
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
     * الحصول على معلومات عمولة محددة
     * 
     * @param int $id معرف العمولة
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT c.*, s.amount as sale_amount, s.sale_date, u.full_name as salesperson_name, cl.name as client_name
                  FROM " . $this->table . " c
                  LEFT JOIN sales s ON c.sale_id = s.id
                  LEFT JOIN users u ON c.salesperson_id = u.id
                  LEFT JOIN clients cl ON s.client_id = cl.id
                  WHERE c.id = ?";
        
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
            $this->sale_id = $row['sale_id'];
            $this->salesperson_id = $row['salesperson_id'];
            $this->amount = $row['amount'];
            $this->status = $row['status'];
            $this->payment_date = $row['payment_date'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على عمولات مندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $status حالة العمولة (اختياري)
     * @return array مصفوفة من العمولات
     */
    public function getCommissionsBySalesperson($salesperson_id, $status = null) {
        // استعلام
        $query = "SELECT c.*, s.amount as sale_amount, s.sale_date, cl.name as client_name
                  FROM " . $this->table . " c
                  LEFT JOIN sales s ON c.sale_id = s.id
                  LEFT JOIN clients cl ON s.client_id = cl.id
                  WHERE c.salesperson_id = ?";
        
        // إضافة شرط الحالة
        if ($status) {
            $query .= " AND c.status = ?";
        }
        
        $query .= " ORDER BY c.created_at DESC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($status) {
            $stmt->bind_param("is", $salesperson_id, $status);
        } else {
            $stmt->bind_param("i", $salesperson_id);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $commissions = [];
        while ($row = $result->fetch_assoc()) {
            $commissions[] = $row;
        }
        
        return $commissions;
    }
    
    /**
     * الحصول على إحصائيات العمولات لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $period الفترة (شهر، سنة، الكل)
     * @return array إحصائيات العمولات
     */
    public function getCommissionStats($salesperson_id, $period = 'all') {
        // تحديد شرط الفترة
        $date_condition = '';
        
        if ($period === 'month') {
            $date_condition = "AND MONTH(c.created_at) = MONTH(CURRENT_DATE()) AND YEAR(c.created_at) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'year') {
            $date_condition = "AND YEAR(c.created_at) = YEAR(CURRENT_DATE())";
        }
        
        // استعلام
        $query = "SELECT 
                    SUM(CASE WHEN c.status = 'pending' THEN c.amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN c.status = 'paid' THEN c.amount ELSE 0 END) as paid_amount,
                    COUNT(CASE WHEN c.status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN c.status = 'paid' THEN 1 END) as paid_count
                  FROM " . $this->table . " c
                  WHERE c.salesperson_id = ? " . $date_condition;
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // التحقق من القيم NULL وتعيين الافتراضيات
        $stats = [
            'pending_amount' => $row['pending_amount'] ? $row['pending_amount'] : 0,
            'paid_amount' => $row['paid_amount'] ? $row['paid_amount'] : 0,
            'pending_count' => $row['pending_count'] ? $row['pending_count'] : 0,
            'paid_count' => $row['paid_count'] ? $row['paid_count'] : 0,
            'total_amount' => ($row['pending_amount'] ? $row['pending_amount'] : 0) + ($row['paid_amount'] ? $row['paid_amount'] : 0),
            'total_count' => ($row['pending_count'] ? $row['pending_count'] : 0) + ($row['paid_count'] ? $row['paid_count'] : 0)
        ];
        
        return $stats;
    }
}
?>