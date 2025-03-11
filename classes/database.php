<?php
/**
 * فئة الاتصال بقاعدة البيانات
 * تدير كافة اتصالات قاعدة البيانات للنظام
 * 
 * @version 1.0
 */
class Database {
    // معلمات الاتصال بقاعدة البيانات
    private $host = "localhost";
    private $db_name = "real_estate_marketing";
    private $username = "root";      // قم بتغييره إلى اسم المستخدم الخاص بك
    private $password = "";          // قم بتغييره إلى كلمة المرور الخاصة بك
    private $conn;
    
    /**
     * الحصول على اتصال قاعدة البيانات
     * 
     * @return mysqli كائن اتصال mysqli
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            
            // التحقق من حالة الاتصال
            if ($this->conn->connect_error) {
                throw new Exception("فشل الاتصال بقاعدة البيانات: " . $this->conn->connect_error);
            }
            
            // تعيين ترميز الاتصال إلى UTF-8
            $this->conn->set_charset("utf8");
        } catch(Exception $e) {
            echo "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
        }
        
        return $this->conn;
    }
    
    /**
     * تنفيذ استعلام مع تحضير مسبق
     * 
     * @param string $query نص الاستعلام
     * @param array $params المعلمات المراد تمريرها
     * @param string $types أنواع المعلمات (s للنصوص، i للأرقام الصحيحة، d للأرقام العشرية، b للبيانات الثنائية)
     * @return mysqli_stmt كائن الاستعلام المحضر
     */
    public function executeQuery($query, $params = [], $types = null) {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("خطأ في تحضير الاستعلام: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                // إذا لم يتم تحديد أنواع المعلمات، قم بتحديدها تلقائيًا
                if ($types === null) {
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } elseif (is_string($param)) {
                            $types .= 's';
                        } else {
                            $types .= 'b';
                        }
                    }
                }
                
                // ربط المعلمات
                $stmt->bind_param($types, ...$params);
            }
            
            // تنفيذ الاستعلام
            $stmt->execute();
            
            return $stmt;
        } catch(Exception $e) {
            echo "خطأ في تنفيذ الاستعلام: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع مصفوفة من النتائج
     * 
     * @param string $query نص الاستعلام
     * @param array $params المعلمات المراد تمريرها
     * @param string $types أنواع المعلمات
     * @return array مصفوفة من النتائج
     */
    public function getRecords($query, $params = [], $types = null) {
        $stmt = $this->executeQuery($query, $params, $types);
        
        if (!$stmt) {
            return [];
        }
        
        $result = $stmt->get_result();
        $records = [];
        
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        
        $stmt->close();
        
        return $records;
    }
    
    /**
     * تنفيذ استعلام SELECT وإرجاع سجل واحد
     * 
     * @param string $query نص الاستعلام
     * @param array $params المعلمات المراد تمريرها
     * @param string $types أنواع المعلمات
     * @return array|null السجل الناتج أو null إذا لم يوجد
     */
    public function getRecord($query, $params = [], $types = null) {
        $records = $this->getRecords($query, $params, $types);
        
        return empty($records) ? null : $records[0];
    }
    
    /**
     * تنفيذ استعلام INSERT وإرجاع معرف السجل المدرج
     * 
     * @param string $query نص الاستعلام
     * @param array $params المعلمات المراد تمريرها
     * @param string $types أنواع المعلمات
     * @return int|bool معرف السجل المدرج أو false في حالة الفشل
     */
    public function insert($query, $params = [], $types = null) {
        $stmt = $this->executeQuery($query, $params, $types);
        
        if (!$stmt) {
            return false;
        }
        
        $insert_id = $this->conn->insert_id;
        $stmt->close();
        
        return $insert_id;
    }
    
    /**
     * تنفيذ استعلام UPDATE أو DELETE وإرجاع عدد الصفوف المتأثرة
     * 
     * @param string $query نص الاستعلام
     * @param array $params المعلمات المراد تمريرها
     * @param string $types أنواع المعلمات
     * @return int|bool عدد الصفوف المتأثرة أو false في حالة الفشل
     */
    public function update($query, $params = [], $types = null) {
        $stmt = $this->executeQuery($query, $params, $types);
        
        if (!$stmt) {
            return false;
        }
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
    }
    
    /**
     * هروب النصوص لمنع هجمات حقن SQL
     * 
     * @param string $string النص المراد معالجته
     * @return string النص المعالج
     */
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * إغلاق اتصال قاعدة البيانات
     */
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * دالة المصفي عند انتهاء البرنامج
     */
    public function __destruct() {
        $this->closeConnection();
    }
}
?>