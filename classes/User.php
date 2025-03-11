<?php
/**
 * فئة المستخدم
 * تتعامل مع كافة العمليات المتعلقة بالمستخدمين في النظام
 */
class User {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'users';
    
    // خصائص المستخدم
    public $id;
    public $username;
    public $password;
    public $full_name;
    public $email;
    public $phone;
    public $role;
    public $manager_id;
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
     * تسجيل دخول مستخدم
     * 
     * @param string $username اسم المستخدم
     * @param string $password كلمة المرور
     * @return array بيانات المستخدم إذا كان التسجيل ناجحًا، وإلا فارغة
     */
    public function login($username, $password) {
        // استعلام
        $query = "SELECT id, username, password, full_name, email, phone, role, manager_id 
                  FROM " . $this->table . " 
                  WHERE username = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("s", $username);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // التحقق من كلمة المرور
            if (password_verify($password, $row['password'])) {
                return $row;
            }
        }
        
        return [];
    }
    
    /**
     * إنشاء مستخدم جديد
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (username, password, full_name, email, phone, role, manager_id)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // ربط المعلمات
        $stmt->bind_param("ssssssi", 
            $this->username, 
            $this->password, 
            $this->full_name, 
            $this->email,
            $this->phone,
            $this->role,
            $this->manager_id
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث معلومات مستخدم
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function update() {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET full_name = ?,
                      email = ?,
                      phone = ?,
                      role = ?,
                      manager_id = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // ربط المعلمات
        $stmt->bind_param("ssssii", 
            $this->full_name, 
            $this->email, 
            $this->phone, 
            $this->role,
            $this->manager_id,
            $this->id
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * تغيير كلمة المرور
     * 
     * @param int $id معرف المستخدم
     * @param string $new_password كلمة المرور الجديدة
     * @return boolean نجاح أو فشل العملية
     */
    public function changePassword($id, $new_password) {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET password = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تشفير كلمة المرور
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // ربط المعلمات
        $stmt->bind_param("si", $hashed_password, $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف مستخدم
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
     * الحصول على معلومات مستخدم محدد
     * 
     * @param int $id معرف المستخدم
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
        
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
            $this->username = $row['username'];
            $this->full_name = $row['full_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->manager_id = $row['manager_id'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على جميع المستخدمين
     * 
     * @param array $filters مصفوفة من عوامل التصفية
     * @return array مصفوفة من المستخدمين
     */
    public function readAll($filters = []) {
        // استعلام
        $query = "SELECT * FROM " . $this->table;
        
        // إضافة شرط الدور
        $where_clauses = [];
        $params = [];
        $types = "";
        
        if (isset($filters['role']) && !empty($filters['role'])) {
            $where_clauses[] = "role = ?";
            $params[] = $filters['role'];
            $types .= "s";
        }
        
        // إضافة شرط البحث
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search_term = "%" . $filters['search'] . "%";
            $where_clauses[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $types .= "sss";
        }
        
        // بناء جملة WHERE إذا وجدت شروط
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        // إضافة ترتيب
        if (isset($filters['sort_field']) && !empty($filters['sort_field'])) {
            $sort_field = $filters['sort_field'];
            $sort_direction = (isset($filters['sort_direction']) && strtoupper($filters['sort_direction']) === 'DESC') ? 'DESC' : 'ASC';
            $query .= " ORDER BY " . $sort_field . " " . $sort_direction;
        } else {
            $query .= " ORDER BY full_name ASC";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات إذا وجدت
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    /**
     * الحصول على مندوبي المبيعات التابعين لمدير معين
     * 
     * @param int $manager_id معرف المدير
     * @return array مصفوفة من مندوبي المبيعات
     */
    public function getSalespeopleByManager($manager_id) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE manager_id = ? AND role = 'salesperson'
                  ORDER BY full_name";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $manager_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $salespeople = [];
        while ($row = $result->fetch_assoc()) {
            $salespeople[] = $row;
        }
        
        return $salespeople;
    }
    
    /**
     * التحقق من وجود اسم المستخدم
     * 
     * @param string $username اسم المستخدم
     * @return boolean نتيجة التحقق
     */
    public function isUsernameExists($username) {
        // استعلام
        $query = "SELECT id FROM " . $this->table . " WHERE username = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("s", $username);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * التحقق من وجود البريد الإلكتروني
     * 
     * @param string $email البريد الإلكتروني
     * @param int $exclude_id معرف المستخدم المستثنى (اختياري)
     * @return boolean نتيجة التحقق
     */
    public function isEmailExists($email, $exclude_id = null) {
        // استعلام
        $query = "SELECT id FROM " . $this->table . " WHERE email = ?";
        
        // إضافة شرط الاستثناء
        if ($exclude_id) {
            $query .= " AND id != ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($exclude_id) {
            $stmt->bind_param("si", $email, $exclude_id);
        } else {
            $stmt->bind_param("s", $email);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * الحصول على اسم المستخدم بناءً على المعرف
     * 
     * @param int $id معرف المستخدم
     * @return string اسم المستخدم
     */
    public function getUserName($id) {
        // استعلام
        $query = "SELECT full_name FROM " . $this->table . " WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['full_name'];
        }
        
        return "غير معروف";
    }
    
    /**
     * الحصول على معلومات المدير المسؤول عن مندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @return array|null معلومات المدير
     */
    public function getManagerBySalesperson($salesperson_id) {
        // الحصول على معرف المدير
        $query = "SELECT manager_id FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $salesperson_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $manager_id = $row['manager_id'];
            
            // الحصول على معلومات المدير إذا وجد
            if ($manager_id) {
                $query = "SELECT * FROM " . $this->table . " WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $manager_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
        }
        
        return null;
    }
}
?>