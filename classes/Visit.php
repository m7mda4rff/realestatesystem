<?php
/**
 * فئة الزيارات الخارجية
 * تتعامل مع كافة العمليات المتعلقة بالزيارات الخارجية للمندوبين
 * 
 * @version 1.0
 */
class Visit {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'visits';
    
    // خصائص الزيارة
    public $id;
    public $salesperson_id;
    public $company_name;
    public $client_name;
    public $client_phone;
    public $visit_time;
    public $purpose;
    public $outcome;
    public $visit_status;
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
     * الحصول على جميع الزيارات
     * 
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return array مصفوفة من الزيارات
     */
    public function readAll($filters = []) {
        // بناء جملة الاستعلام
        $query = "SELECT v.*, u.full_name as salesperson_name
                  FROM " . $this->table . " v
                  LEFT JOIN users u ON v.salesperson_id = u.id";
        
        // إضافة شروط التصفية
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['salesperson_id'])) {
            $whereClause[] = "v.salesperson_id = ?";
            $params[] = $filters['salesperson_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['visit_status'])) {
            $whereClause[] = "v.visit_status = ?";
            $params[] = $filters['visit_status'];
            $types .= 's';
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $whereClause[] = "DATE(v.visit_time) BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        } elseif (!empty($filters['start_date'])) {
            $whereClause[] = "DATE(v.visit_time) >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        } elseif (!empty($filters['end_date'])) {
            $whereClause[] = "DATE(v.visit_time) <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = "%" . $filters['search'] . "%";
            $whereClause[] = "(v.company_name LIKE ? OR v.client_name LIKE ? OR v.client_phone LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY v.visit_time " . (!empty($filters['order']) && $filters['order'] === 'asc' ? 'ASC' : 'DESC');
        
        // إضافة الحد والإزاحة (للتصفح الصفحات)
        if (!empty($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $filters['limit'];
            $types .= 'i';
            
            if (!empty($filters['offset'])) {
                $query .= " OFFSET ?";
                $params[] = $filters['offset'];
                $types .= 'i';
            }
        }
        
        // تحضير وتنفيذ الاستعلام
        $stmt = $this->conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * إنشاء زيارة جديدة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (salesperson_id, company_name, client_name, client_phone, 
                  visit_time, purpose, visit_status, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        $this->company_name = htmlspecialchars(strip_tags($this->company_name));
        $this->client_name = htmlspecialchars(strip_tags($this->client_name));
        $this->client_phone = htmlspecialchars(strip_tags($this->client_phone));
        $this->visit_time = htmlspecialchars(strip_tags($this->visit_time));
        $this->purpose = htmlspecialchars(strip_tags($this->purpose));
        $this->visit_status = htmlspecialchars(strip_tags($this->visit_status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        
        // ربط المعلمات
        $stmt->bind_param("isssssss", 
            $this->salesperson_id, 
            $this->company_name, 
            $this->client_name, 
            $this->client_phone,
            $this->visit_time,
            $this->purpose,
            $this->visit_status,
            $this->notes
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            $this->id = $this->conn->insert_id;
            
            // إنشاء إشعار بالزيارة الجديدة
            $this->createNotification('create');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث زيارة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function update() {
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET company_name = ?,
                      client_name = ?,
                      client_phone = ?,
                      visit_time = ?,
                      purpose = ?,
                      outcome = ?,
                      visit_status = ?,
                      notes = ?
                  WHERE id = ? AND salesperson_id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->company_name = htmlspecialchars(strip_tags($this->company_name));
        $this->client_name = htmlspecialchars(strip_tags($this->client_name));
        $this->client_phone = htmlspecialchars(strip_tags($this->client_phone));
        $this->visit_time = htmlspecialchars(strip_tags($this->visit_time));
        $this->purpose = htmlspecialchars(strip_tags($this->purpose));
        $this->outcome = htmlspecialchars(strip_tags($this->outcome));
        $this->visit_status = htmlspecialchars(strip_tags($this->visit_status));
        $this->notes = htmlspecialchars(strip_tags($this->notes));
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        
        // ربط المعلمات
        $stmt->bind_param("ssssssssii", 
            $this->company_name, 
            $this->client_name, 
            $this->client_phone,
            $this->visit_time,
            $this->purpose,
            $this->outcome,
            $this->visit_status,
            $this->notes,
            $this->id,
            $this->salesperson_id
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // إنشاء إشعار بتحديث الزيارة
            $this->createNotification('update');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف زيارة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function delete() {
        // قراءة معلومات الزيارة قبل الحذف
        $this->readOne($this->id);
        
        // استعلام
        $query = "DELETE FROM " . $this->table . " WHERE id = ? AND salesperson_id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        
        // ربط المعلمات
        $stmt->bind_param("ii", $this->id, $this->salesperson_id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // إنشاء إشعار بحذف الزيارة
            $this->createNotification('delete');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على تفاصيل زيارة محددة
     * 
     * @param int $id معرف الزيارة
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT v.*, u.full_name as salesperson_name, u.email as salesperson_email
                  FROM " . $this->table . " v
                  LEFT JOIN users u ON v.salesperson_id = u.id
                  WHERE v.id = ?";
        
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
            $this->company_name = $row['company_name'];
            $this->client_name = $row['client_name'];
            $this->client_phone = $row['client_phone'];
            $this->visit_time = $row['visit_time'];
            $this->purpose = $row['purpose'];
            $this->outcome = $row['outcome'];
            $this->visit_status = $row['visit_status'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على جميع الزيارات الخاصة بمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return array مصفوفة من الزيارات
     */
    public function getVisitsBySalesperson($salesperson_id, $filters = []) {
        $filters['salesperson_id'] = $salesperson_id;
        return $this->readAll($filters);
    }
    
    /**
     * الحصول على الزيارات القادمة لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param int $limit عدد السجلات المطلوبة
     * @return array مصفوفة من الزيارات القادمة
     */
    public function getUpcomingVisits($salesperson_id, $limit = 5) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND visit_time >= NOW() 
                  AND visit_status = 'planned' 
                  ORDER BY visit_time ASC 
                  LIMIT ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("ii", $salesperson_id, $limit);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * تحديث حالة الزيارة
     * 
     * @param int $id معرف الزيارة
     * @param string $status الحالة الجديدة
     * @param string $outcome نتيجة الزيارة
     * @return boolean نجاح أو فشل العملية
     */
    public function updateStatus($id, $status, $outcome = null) {
        // التحقق من صحة الحالة
        $valid_statuses = ['planned', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        // قراءة معلومات الزيارة قبل التحديث
        $this->readOne($id);
        
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET visit_status = ?,
                      outcome = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $status = htmlspecialchars(strip_tags($status));
        $outcome = $outcome ? htmlspecialchars(strip_tags($outcome)) : null;
        $id = htmlspecialchars(strip_tags($id));
        
        // ربط المعلمات
        $stmt->bind_param("ssi", $status, $outcome, $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // تحديث الخصائص
            $this->visit_status = $status;
            $this->outcome = $outcome;
            
            // إنشاء إشعار بتحديث حالة الزيارة
            $this->createNotification('status');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على إحصائيات الزيارات
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $period الفترة (شهر، سنة)
     * @return array إحصائيات الزيارات
     */
    public function getVisitStats($salesperson_id, $period = 'month') {
        $date_condition = '';
        
        if ($period === 'month') {
            $date_condition = "AND MONTH(visit_time) = MONTH(CURRENT_DATE()) AND YEAR(visit_time) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'year') {
            $date_condition = "AND YEAR(visit_time) = YEAR(CURRENT_DATE())";
        }
        
        // استعلام
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN visit_status = 'planned' THEN 1 ELSE 0 END) as planned,
                    SUM(CASE WHEN visit_status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN visit_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                  FROM " . $this->table . "
                  WHERE salesperson_id = ? " . $date_condition;
        
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
            'total' => $row['total'] ?? 0,
            'planned' => $row['planned'] ?? 0,
            'completed' => $row['completed'] ?? 0,
            'cancelled' => $row['cancelled'] ?? 0
        ];
        
        return $stats;
    }
    
    /**
     * الحصول على زيارات اليوم لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @return array مصفوفة من زيارات اليوم
     */
    public function getTodayVisits($salesperson_id) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND DATE(visit_time) = CURRENT_DATE() 
                  ORDER BY visit_time ASC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * الحصول على الزيارات حسب التاريخ
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $date التاريخ (YYYY-MM-DD)
     * @return array مصفوفة من الزيارات
     */
    public function getVisitsByDate($salesperson_id, $date) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND DATE(visit_time) = ? 
                  ORDER BY visit_time ASC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("is", $salesperson_id, $date);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * الحصول على بيانات زيارات الشهر بتنسيق التقويم
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param int $month الشهر (1-12)
     * @param int $year السنة
     * @return array بيانات التقويم
     */
    public function getCalendarData($salesperson_id, $month, $year) {
        // استعلام
        $query = "SELECT id, company_name, client_name, 
                         DATE(visit_time) as visit_date, 
                         TIME_FORMAT(visit_time, '%H:%i') as visit_time, 
                         visit_status 
                  FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND MONTH(visit_time) = ? 
                  AND YEAR(visit_time) = ? 
                  ORDER BY visit_time ASC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("iii", $salesperson_id, $month, $year);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        // تنظيم البيانات حسب التاريخ
        $calendar_data = [];
        foreach ($visits as $visit) {
            $date = $visit['visit_date'];
            if (!isset($calendar_data[$date])) {
                $calendar_data[$date] = [];
            }
            $calendar_data[$date][] = $visit;
        }
        
        return $calendar_data;
    }
    
    /**
     * التحقق من تعارض المواعيد
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $visit_time وقت الزيارة
     * @param int $duration مدة الزيارة بالدقائق (اختياري، الافتراضي 60 دقيقة)
     * @param int $exclude_id معرف الزيارة المستثناة (اختياري)
     * @return boolean هل يوجد تعارض
     */
    public function checkTimeConflict($salesperson_id, $visit_time, $duration = 60, $exclude_id = null) {
        // حساب وقت بداية ونهاية الزيارة
        $start_time = date('Y-m-d H:i:s', strtotime($visit_time));
        $end_time = date('Y-m-d H:i:s', strtotime($visit_time . ' +' . $duration . ' minutes'));
        
        // استعلام
        $query = "SELECT COUNT(*) as count FROM " . $this->table . "
                  WHERE salesperson_id = ? 
                  AND visit_status = 'planned'
                  AND (
                      (visit_time <= ? AND DATE_ADD(visit_time, INTERVAL 60 MINUTE) >= ?) OR
                      (visit_time >= ? AND visit_time <= ?)
                  )";
        
        // إضافة شرط الاستثناء
        if ($exclude_id) {
            $query .= " AND id != ?";
        }
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        if ($exclude_id) {
            $stmt->bind_param("isssi", $salesperson_id, $end_time, $start_time, $start_time, $end_time, $exclude_id);
        } else {
            $stmt->bind_param("isss", $salesperson_id, $end_time, $start_time, $start_time, $end_time);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] > 0;
    }
    
    /**
     * إنشاء إشعار للمندوب
     * 
     * @param string $action نوع الإجراء (create, update, delete, status)
     * @return boolean نجاح أو فشل العملية
     */
    private function createNotification($action = 'create') {
        // تضمين فئة الإشعارات
        require_once 'Notification.php';
        
        // إنشاء كائن من فئة الإشعارات
        $notification = new Notification($this->conn);
        
        // تحديد عنوان ونص الإشعار بناءً على الإجراء
        $title = '';
        $message = '';
        
        switch ($action) {
            case 'create':
                $title = 'زيارة جديدة';
                $message = 'تم إضافة زيارة جديدة لـ ' . $this->company_name . ' بتاريخ ' . date('Y-m-d', strtotime($this->visit_time));
                break;
            case 'update':
                $title = 'تحديث زيارة';
                $message = 'تم تحديث بيانات زيارة ' . $this->company_name;
                break;
            case 'delete':
                $title = 'حذف زيارة';
                $message = 'تم حذف زيارة ' . $this->company_name;
                break;
            case 'status':
                $status_text = $this->visit_status === 'completed' ? 'مكتملة' : ($this->visit_status === 'cancelled' ? 'ملغية' : 'مخططة');
                $title = 'تغيير حالة زيارة';
                $message = 'تم تغيير حالة زيارة ' . $this->company_name . ' إلى ' . $status_text;
                break;
        }
        
        // إرسال الإشعار للمندوب
        return $notification->send(
            $this->salesperson_id,
            $title,
            $message,
            'visit',
            $this->id,
            'visits'
        );
    }
    
    /**
     * الحصول على عدد زيارات اليوم
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @return int عدد زيارات اليوم
     */
    public function getTodayVisitsCount($salesperson_id) {
        // استعلام
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND DATE(visit_time) = CURRENT_DATE()";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'];
    }
    
    /**
     * الحصول على إحصائيات الزيارات حسب الشركة
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $period الفترة (month, year, all)
     * @return array إحصائيات الزيارات
     */
    public function getVisitStatsByCompany($salesperson_id, $period = 'month') {
        $date_condition = '';
        
        if ($period === 'month') {
            $date_condition = "AND MONTH(visit_time) = MONTH(CURRENT_DATE()) AND YEAR(visit_time) = YEAR(CURRENT_DATE())";
        } elseif ($period === 'year') {
            $date_condition = "AND YEAR(visit_time) = YEAR(CURRENT_DATE())";
        }
        
        // استعلام
        $query = "SELECT 
                    company_name,
                    COUNT(*) as total,
                    SUM(CASE WHEN visit_status = 'completed' THEN 1 ELSE 0 END) as completed
                  FROM " . $this->table . "
                  WHERE salesperson_id = ? " . $date_condition . "
                  GROUP BY company_name
                  ORDER BY total DESC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        return $stats;
    }
    
    /**
     * الحصول على الزيارات المقبلة اليوم
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @return array مصفوفة من الزيارات المقبلة اليوم
     */
    public function getUpcomingTodayVisits($salesperson_id) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND DATE(visit_time) = CURRENT_DATE() 
                  AND visit_time >= NOW() 
                  AND visit_status = 'planned' 
                  ORDER BY visit_time ASC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $salesperson_id);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * الحصول على الزيارات التي تم إكمالها
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param int $limit عدد السجلات (اختياري)
     * @return array مصفوفة من الزيارات المكتملة
     */
    public function getCompletedVisits($salesperson_id, $limit = null) {
        // استعلام
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE salesperson_id = ? 
                  AND visit_status = 'completed' 
                  ORDER BY visit_time DESC";
        
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
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * البحث عن زيارات
     * 
     * @param string $term مصطلح البحث
     * @param int $salesperson_id معرف مندوب المبيعات (اختياري)
     * @param int $limit عدد النتائج (اختياري)
     * @return array مصفوفة من نتائج البحث
     */
    public function search($term, $salesperson_id = null, $limit = 10) {
        // استعلام
        $query = "SELECT v.*, u.full_name as salesperson_name
                  FROM " . $this->table . " v
                  LEFT JOIN users u ON v.salesperson_id = u.id
                  WHERE (v.company_name LIKE ? OR v.client_name LIKE ? OR v.client_phone LIKE ?)";
        
        // إضافة شرط المندوب
        if ($salesperson_id) {
            $query .= " AND v.salesperson_id = ?";
        }
        
        // إضافة الترتيب والحد
        $query .= " ORDER BY v.visit_time DESC LIMIT ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // إعداد معلمات البحث
        $search_term = "%" . $term . "%";
        
        // ربط المعلمات
        if ($salesperson_id) {
            $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $salesperson_id, $limit);
        } else {
            $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $limit);
        }
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $visits = [];
        while ($row = $result->fetch_assoc()) {
            $visits[] = $row;
        }
        
        return $visits;
    }
    
    /**
     * الحصول على عدد الزيارات الكلي
     * 
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return int عدد الزيارات
     */
    public function getTotalCount($filters = []) {
        // بناء جملة الاستعلام
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " v";
        
        // إضافة شروط التصفية
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['salesperson_id'])) {
            $whereClause[] = "v.salesperson_id = ?";
            $params[] = $filters['salesperson_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['visit_status'])) {
            $whereClause[] = "v.visit_status = ?";
            $params[] = $filters['visit_status'];
            $types .= 's';
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $whereClause[] = "DATE(v.visit_time) BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        } elseif (!empty($filters['start_date'])) {
            $whereClause[] = "DATE(v.visit_time) >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        } elseif (!empty($filters['end_date'])) {
            $whereClause[] = "DATE(v.visit_time) <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        // تحضير وتنفيذ الاستعلام
        $stmt = $this->conn->prepare($query);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'];
    }
    
    /**
     * تحويل زيارة إلى مبيعة
     * 
     * @param int $id معرف الزيارة
     * @param array $sale_data بيانات المبيعة
     * @return boolean نجاح أو فشل العملية
     */
    public function convertToSale($id, $sale_data) {
        // قراءة معلومات الزيارة
        $this->readOne($id);
        
        // تحديث حالة الزيارة إلى مكتملة
        $this->updateStatus($id, 'completed', $sale_data['notes'] ?? 'تم تحويل الزيارة إلى مبيعة');
        
        // تضمين فئة المبيعات
        require_once 'Sale.php';
        
        // إنشاء كائن من فئة المبيعات
        $sale = new Sale($this->conn);
        
        // تعيين خصائص المبيعة
        $sale->client_id = $sale_data['client_id'];
        $sale->salesperson_id = $this->salesperson_id;
        $sale->amount = $sale_data['amount'];
        $sale->commission_rate = $sale_data['commission_rate'] ?? COMMISSION_DEFAULT_RATE;
        $sale->commission_amount = Sale::calculateCommission($sale_data['amount'], $sale->commission_rate);
        $sale->description = "تم إنشاء هذه المبيعة من زيارة لـ " . $this->company_name;
        $sale->sale_date = date('Y-m-d');
        $sale->payment_status = $sale_data['payment_status'] ?? 'pending';
        $sale->created_by = $sale_data['created_by'] ?? $this->salesperson_id;
        
        // محاولة إنشاء المبيعة
        return $sale->create();
    }
}
?>