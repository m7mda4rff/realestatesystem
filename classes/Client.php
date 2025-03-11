<?php
/**
 * فئة العملاء
 * تتعامل مع كافة العمليات المتعلقة بالعملاء في النظام
 */
class Client {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'clients';
    
    // خصائص العميل
    public $id;
    public $name;
    public $phone;
    public $email;
    public $address;
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
     * إنشاء عميل جديد
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (name, phone, email, address, notes, created_by)
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->created_by = htmlspecialchars(strip_tags($this->created_by));
        
        // ربط المعلمات
        $stmt->bind_param("sssssi", 
            $this->name, 
            $this->phone, 
            $this->email, 
            $this->address,
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
     * تحديث معلومات عميل
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function update() {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET name = ?,
                      phone = ?,
                      email = ?,
                      address = ?,
                      notes = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->address = htmlspecialchars(strip_tags($this->address));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // ربط المعلمات
        $stmt->bind_param("sssssi", 
            $this->name, 
            $this->phone, 
            $this->email, 
            $this->address,
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
     * حذف عميل
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function delete() {
        // استعلام للتحقق من وجود مبيعات مرتبطة بالعميل
        $check_query = "SELECT COUNT(*) as count FROM sales WHERE client_id = ?";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bind_param("i", $this->id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        // إذا كانت هناك مبيعات مرتبطة، لا يمكن حذف العميل
        if ($row['count'] > 0) {
            return false;
        }
        
        // استعلام الحذف
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
     * الحصول على معلومات عميل محدد
     * 
     * @param int $id معرف العميل
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT c.*, u.full_name as created_by_name
                  FROM " . $this->table . " c
                  LEFT JOIN users u ON c.created_by = u.id
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
            $this->name = $row['name'];
            $this->phone = $row['phone'];
            $this->email = $row['email'];
            $this->address = $row['address'];
            $this->notes = $row['notes'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على جميع العملاء
     * 
     * @param string $search_term مصطلح البحث (اختياري)
     * @return array مصفوفة من العملاء
     */
    public function readAll($search_term = null) {
        // استعلام
        $query = "SELECT c.*, u.full_name as created_by_name
                  FROM " . $this->table . " c
                  LEFT JOIN users u ON c.created_by = u.id";
        
        // إضافة شرط البحث
        if ($search_term) {
            $query .= " WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
        }
        
        $query .= " ORDER BY c.name";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($search_term) {
            $search_param = "%" . $search_term . "%";
            $stmt->bind_param("sss", $search_param, $search_param, $search_param);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        
        return $clients;
    }
    
    /**
     * البحث عن العملاء
     * 
     * @param string $term مصطلح البحث
     * @param int $limit عدد النتائج (اختياري)
     * @return array مصفوفة من العملاء
     */
    public function search($term, $limit = 10) {
        // استعلام
        $query = "SELECT id, name, phone, email 
                  FROM " . $this->table . "
                  WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
                  ORDER BY name
                  LIMIT ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // إعداد معلمات البحث
        $search_term = "%" . $term . "%";
        
        // ربط المعلمات
        $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $limit);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        
        return $clients;
    }
    
    /**
     * الحصول على عدد مبيعات العميل
     * 
     * @param int $client_id معرف العميل
     * @return int عدد المبيعات
     */
    public function getSalesCount($client_id) {
        // استعلام
        $query = "SELECT COUNT(*) as count FROM sales WHERE client_id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $client_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'];
    }
}
?>