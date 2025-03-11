<?php
/**
 * فئة الاتصال بقاعدة البيانات
 * تدير كافة اتصالات قاعدة البيانات للنظام
 */
class Database {
    // معلمات الاتصال بقاعدة البيانات
    private $host = "localhost";
    private $db_name = "bestbmfm_salesb";
    private $username = "bestbmfm_salesb";      // قم بتغييره إلى اسم المستخدم الخاص بك
    private $password = "m.w4qE,Bew)c";          // قم بتغييره إلى كلمة المرور الخاصة بك
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
            $this->conn->set_charset("utf8");
        } catch(Exception $e) {
            echo "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
        }
        
        return $this->conn;
    }
}
?>