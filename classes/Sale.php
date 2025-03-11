<?php
/**
 * فئة المبيعات
 * تتعامل مع كافة العمليات المتعلقة بالمبيعات في النظام
 * 
 * @version 1.0
 */
class Sale {
    // متغيرات الاتصال بقاعدة البيانات
    private $conn;
    private $table = 'sales';
    
    // خصائص المبيعات
    public $id;
    public $client_id;
    public $salesperson_id;
    public $amount;
    public $commission_rate;
    public $commission_amount;
    public $description;
    public $sale_date;
    public $payment_status;
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
     * الحصول على جميع المبيعات
     * 
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return array مصفوفة من المبيعات
     */
    public function readAll($filters = []) {
        // بناء جملة الاستعلام
        $query = "SELECT s.*, c.name as client_name, u.full_name as salesperson_name, 
                  u2.full_name as created_by_name
                  FROM " . $this->table . " s
                  LEFT JOIN clients c ON s.client_id = c.id
                  LEFT JOIN users u ON s.salesperson_id = u.id
                  LEFT JOIN users u2 ON s.created_by = u2.id";
        
        // إضافة شروط التصفية
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['client_id'])) {
            $whereClause[] = "s.client_id = ?";
            $params[] = $filters['client_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['salesperson_id'])) {
            $whereClause[] = "s.salesperson_id = ?";
            $params[] = $filters['salesperson_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['payment_status'])) {
            $whereClause[] = "s.payment_status = ?";
            $params[] = $filters['payment_status'];
            $types .= 's';
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $whereClause[] = "s.sale_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        } elseif (!empty($filters['start_date'])) {
            $whereClause[] = "s.sale_date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        } elseif (!empty($filters['end_date'])) {
            $whereClause[] = "s.sale_date <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = "%" . $filters['search'] . "%";
            $whereClause[] = "(c.name LIKE ? OR u.full_name LIKE ? OR s.description LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'sss';
        }
        
        if (!empty($whereClause)) {
            $query .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        // إضافة الترتيب
        $query .= " ORDER BY s.sale_date DESC";
        
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
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        
        return $sales;
    }
    
    /**
     * الحصول على مبيعات مندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return array مصفوفة من المبيعات
     */
    public function getSalesBySalesperson($salesperson_id, $filters = []) {
        $filters['salesperson_id'] = $salesperson_id;
        return $this->readAll($filters);
    }
    
    /**
     * إضافة مبيعة جديدة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function create() {
        // استعلام
        $query = "INSERT INTO " . $this->table . "
                 (client_id, salesperson_id, amount, commission_rate, commission_amount, 
                  description, sale_date, payment_status, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->commission_rate = htmlspecialchars(strip_tags($this->commission_rate));
        $this->commission_amount = htmlspecialchars(strip_tags($this->commission_amount));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->sale_date = htmlspecialchars(strip_tags($this->sale_date));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
        $this->created_by = htmlspecialchars(strip_tags($this->created_by));
        
        // ربط المعلمات
        $stmt->bind_param("iidddsssi", 
            $this->client_id, 
            $this->salesperson_id, 
            $this->amount, 
            $this->commission_rate,
            $this->commission_amount,
            $this->description,
            $this->sale_date,
            $this->payment_status,
            $this->created_by
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // إذا تم الإنشاء بنجاح، قم بإضافة العمولة أيضًا
            $this->id = $this->conn->insert_id;
            
            // إنشاء سجل عمولة
            $commission_query = "INSERT INTO commissions 
                               (sale_id, salesperson_id, amount, status)
                               VALUES (?, ?, ?, 'pending')";
            
            $commission_stmt = $this->conn->prepare($commission_query);
            $commission_stmt->bind_param("iid", 
                $this->id, 
                $this->salesperson_id, 
                $this->commission_amount
            );
            
            // تنفيذ استعلام العمولة
            $commission_stmt->execute();
            
            // تحديث الأهداف المحققة للمندوب
            $this->updateTargetAchievement();
            
            // إنشاء إشعار للمندوب
            $this->createNotification();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على تفاصيل مبيعة محددة
     * 
     * @param int $id معرف المبيعة
     * @return boolean نجاح أو فشل العملية
     */
    public function readOne($id) {
        // استعلام
        $query = "SELECT s.*, c.name as client_name, u.full_name as salesperson_name,
                  u.email as salesperson_email, u.phone as salesperson_phone,
                  u2.full_name as created_by_name
                  FROM " . $this->table . " s
                  LEFT JOIN clients c ON s.client_id = c.id
                  LEFT JOIN users u ON s.salesperson_id = u.id
                  LEFT JOIN users u2 ON s.created_by = u2.id
                  WHERE s.id = ?";
        
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
            $this->client_id = $row['client_id'];
            $this->salesperson_id = $row['salesperson_id'];
            $this->amount = $row['amount'];
            $this->commission_rate = $row['commission_rate'];
            $this->commission_amount = $row['commission_amount'];
            $this->description = $row['description'];
            $this->sale_date = $row['sale_date'];
            $this->payment_status = $row['payment_status'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث مبيعة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function update() {
        // الحصول على قيمة المبيعة الحالية للمقارنة
        $current_amount_query = "SELECT amount, salesperson_id FROM " . $this->table . " WHERE id = ?";
        $current_stmt = $this->conn->prepare($current_amount_query);
        $current_stmt->bind_param("i", $this->id);
        $current_stmt->execute();
        $result = $current_stmt->get_result();
        $row = $result->fetch_assoc();
        $old_amount = $row['amount'];
        $old_salesperson_id = $row['salesperson_id'];
        
        // استعلام التحديث
        $query = "UPDATE " . $this->table . "
                  SET client_id = ?,
                      salesperson_id = ?,
                      amount = ?,
                      commission_rate = ?,
                      commission_amount = ?,
                      description = ?,
                      sale_date = ?,
                      payment_status = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنظيف البيانات
        $this->client_id = htmlspecialchars(strip_tags($this->client_id));
        $this->salesperson_id = htmlspecialchars(strip_tags($this->salesperson_id));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->commission_rate = htmlspecialchars(strip_tags($this->commission_rate));
        $this->commission_amount = htmlspecialchars(strip_tags($this->commission_amount));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->sale_date = htmlspecialchars(strip_tags($this->sale_date));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status));
        
        // ربط المعلمات
        $stmt->bind_param("iidddsssi", 
            $this->client_id, 
            $this->salesperson_id, 
            $this->amount, 
            $this->commission_rate,
            $this->commission_amount,
            $this->description,
            $this->sale_date,
            $this->payment_status,
            $this->id
        );
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // تحديث العمولة المرتبطة
            $commission_query = "UPDATE commissions 
                               SET amount = ?, salesperson_id = ?
                               WHERE sale_id = ?";
            
            $commission_stmt = $this->conn->prepare($commission_query);
            $commission_stmt->bind_param("dii", 
                $this->commission_amount, 
                $this->salesperson_id, 
                $this->id
            );
            
            $commission_stmt->execute();
            
            // تحديث الأهداف المحققة للمندوب إذا تغيرت قيمة المبيعة أو المندوب
            if ($this->amount != $old_amount || $this->salesperson_id != $old_salesperson_id) {
                // إذا تغير المندوب، قم بطرح المبلغ من المندوب القديم
                if ($this->salesperson_id != $old_salesperson_id) {
                    $this->updateTargetAchievement($old_amount, true, $old_salesperson_id);
                    $this->updateTargetAchievement(0, false); // إضافة المبلغ الجديد للمندوب الجديد
                } else {
                    $this->updateTargetAchievement($old_amount);
                }
                
                // إنشاء إشعار بالتحديث
                $this->createNotification('update');
            }
            
            // إذا تم تغيير حالة المبيعة إلى "مدفوعة"، قم بتحديث حالة العمولة أيضًا
            if ($this->payment_status === 'paid') {
                $payment_query = "UPDATE commissions 
                                SET status = 'paid', payment_date = CURRENT_DATE()
                                WHERE sale_id = ? AND status = 'pending'";
                
                $payment_stmt = $this->conn->prepare($payment_query);
                $payment_stmt->bind_param("i", $this->id);
                $payment_stmt->execute();
                
                // إنشاء إشعار بالدفع
                $this->createNotification('payment');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * حذف مبيعة
     * 
     * @return boolean نجاح أو فشل العملية
     */
    public function delete() {
        // الحصول على معلومات المبيعة قبل الحذف
        $this->readOne($this->id);
        
        // استعلام
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("i", $this->id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // حذف سجل العمولة المرتبط
            $commission_query = "DELETE FROM commissions WHERE sale_id = ?";
            $commission_stmt = $this->conn->prepare($commission_query);
            $commission_stmt->bind_param("i", $this->id);
            $commission_stmt->execute();
            
            // تحديث الأهداف المحققة للمندوب بالسالب
            $this->updateTargetAchievement(0, true);
            
            // إنشاء إشعار بالحذف
            $this->createNotification('delete');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على إحصائيات المبيعات الشهرية لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param string $month الشهر بصيغة (YYYY-MM)
     * @return array إحصائيات المبيعات
     */
    public function getMonthlySales($salesperson_id, $month) {
    // استعلام
    $query = "SELECT 
                COUNT(*) as count,
                SUM(amount) as amount,
                SUM(commission_amount) as commission
              FROM " . $this->table . "
              WHERE salesperson_id = ? 
              AND DATE_FORMAT(sale_date, '%Y-%m') = ?";
    
    // تحضير الاستعلام
    $stmt = $this->conn->prepare($query);
    
    // ربط المعلمات
    $stmt->bind_param("is", $salesperson_id, $month);
    
    // تنفيذ الاستعلام
    $stmt->execute();
    
    // الحصول على النتائج
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // التحقق من القيم NULL وتعيين الافتراضيات
    $stats = [
        'count' => $row['count'] ? $row['count'] : 0,
        'amount' => $row['amount'] ? $row['amount'] : 0,
        'commission' => $row['commission'] ? $row['commission'] : 0
    ];
    
    return $stats;
}
    /**
     * الحصول على أحدث المبيعات لمندوب معين
     * 
     * @param int $salesperson_id معرف مندوب المبيعات
     * @param int $limit عدد السجلات المطلوبة
     * @return array مصفوفة من المبيعات الحديثة
     */
    public function getRecentSales($salesperson_id, $limit = 5) {
        // استعلام
        $query = "SELECT s.*, c.name as client_name
                  FROM " . $this->table . " s
                  LEFT JOIN clients c ON s.client_id = c.id
                  WHERE s.salesperson_id = ?
                  ORDER BY s.sale_date DESC
                  LIMIT ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("ii", $salesperson_id, $limit);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $sales[] = $row;
        }
        
        return $sales;
    }
    
    /**
     * الحصول على إحصائيات المبيعات حسب الفترة
     * 
     * @param string $period الفترة (today, week, month, year, all)
     * @return array إحصائيات المبيعات
     */
    public function getSalesStats($period = 'month') {
        // تحديد شرط الفترة
        $date_condition = '';
        
        switch ($period) {
            case 'today':
                $date_condition = "AND sale_date = CURRENT_DATE()";
                break;
            case 'week':
                $date_condition = "AND YEARWEEK(sale_date, 1) = YEARWEEK(CURRENT_DATE(), 1)";
                break;
            case 'month':
                $date_condition = "AND MONTH(sale_date) = MONTH(CURRENT_DATE()) AND YEAR(sale_date) = YEAR(CURRENT_DATE())";
                break;
            case 'year':
                $date_condition = "AND YEAR(sale_date) = YEAR(CURRENT_DATE())";
                break;
            default:
                $date_condition = "";
        }
        
        // استعلام
        $query = "SELECT 
                    COUNT(*) as count,
                    SUM(amount) as amount,
                    SUM(commission_amount) as commission,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN payment_status = 'cancelled' THEN 1 END) as cancelled_count,
                    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN payment_status = 'cancelled' THEN amount ELSE 0 END) as cancelled_amount
                  FROM " . $this->table . "
                  WHERE 1=1 " . $date_condition;
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // التحقق من القيم NULL وتعيين الافتراضيات
        $stats = [
            'count' => $row['count'] ? $row['count'] : 0,
            'amount' => $row['amount'] ? $row['amount'] : 0,
            'commission' => $row['commission'] ? $row['commission'] : 0,
            'paid_count' => $row['paid_count'] ? $row['paid_count'] : 0,
            'pending_count' => $row['pending_count'] ? $row['pending_count'] : 0,
            'cancelled_count' => $row['cancelled_count'] ? $row['cancelled_count'] : 0,
            'paid_amount' => $row['paid_amount'] ? $row['paid_amount'] : 0,
            'pending_amount' => $row['pending_amount'] ? $row['pending_amount'] : 0,
            'cancelled_amount' => $row['cancelled_amount'] ? $row['cancelled_amount'] : 0
        ];
        
        return $stats;
    }
    
    /**
     * الحصول على البيانات للرسم البياني
     * 
     * @param string $period الفترة (month, year)
     * @return array البيانات للرسم البياني
     */
    public function getChartData($period = 'month') {
        $date_format = ($period === 'month') ? '%Y-%m-%d' : '%Y-%m';
        $group_by = ($period === 'month') ? "DATE(sale_date)" : "DATE_FORMAT(sale_date, '%Y-%m')";
        $date_range = '';
        
        if ($period === 'month') {
            $date_range = "AND sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)";
        } elseif ($period === 'year') {
            $date_range = "AND sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)";
        }
        
        // استعلام
        $query = "SELECT 
                    " . $group_by . " as date_label,
                    SUM(amount) as total_amount,
                    COUNT(*) as total_count
                  FROM " . $this->table . "
                  WHERE 1=1 " . $date_range . "
                  GROUP BY " . $group_by . "
                  ORDER BY date_label";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // تنفيذ الاستعلام
        $stmt->execute();
        
        // الحصول على النتائج
        $result = $stmt->get_result();
        
        $chart_data = [];
        while ($row = $result->fetch_assoc()) {
            $chart_data[] = $row;
        }
        
        return $chart_data;
    }
    
    /**
     * تحديث الأهداف المحققة للمندوب
     * 
     * @param float $old_amount القيمة القديمة للمبيعة (في حالة التحديث)
     * @param boolean $is_delete هل هي عملية حذف؟
     * @param int $override_salesperson_id استخدام معرف مندوب آخر (اختياري)
     * @return boolean نجاح أو فشل العملية
     */
    private function updateTargetAchievement($old_amount = 0, $is_delete = false, $override_salesperson_id = null) {
        // تحديد معرف المندوب
        $salesperson_id = $override_salesperson_id ?? $this->salesperson_id;
        
        // الحصول على الهدف الحالي للمندوب
        $target_query = "SELECT id FROM targets 
                        WHERE salesperson_id = ? 
                        AND ? BETWEEN start_date AND end_date";
        
        $target_stmt = $this->conn->prepare($target_query);
        $target_stmt->bind_param("is", $salesperson_id, $this->sale_date);
        $target_stmt->execute();
        $target_result = $target_stmt->get_result();
        
        if ($target_result->num_rows > 0) {
            $target_row = $target_result->fetch_assoc();
            $target_id = $target_row['id'];
            
            // حساب المبلغ الذي سيتم إضافته أو طرحه
            $amount_to_add = 0;
            
            if ($is_delete) {
                // في حالة الحذف، نطرح كامل المبلغ
                $amount_to_add = -$this->amount;
            } else {
                // في حالة الإضافة أو التحديث
                $amount_to_add = $this->amount - $old_amount;
            }
            
            // تحديث الهدف المحقق
            $update_query = "UPDATE targets 
                            SET achieved_amount = achieved_amount + ?
                            WHERE id = ?";
            
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bind_param("di", $amount_to_add, $target_id);
            
            return $update_stmt->execute();
        }
        
        return false;
    }
    
    /**
     * إنشاء إشعار للمندوب
     * 
     * @param string $action نوع الإجراء (create, update, delete, payment)
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
                $title = 'مبيعة جديدة';
                $message = 'تم إضافة مبيعة جديدة بقيمة ' . number_format($this->amount, 2) . ' ج.م وعمولة ' . number_format($this->commission_amount, 2) . ' ج.م';
                break;
            case 'update':
                $title = 'تحديث مبيعة';
                $message = 'تم تحديث بيانات مبيعة بقيمة ' . number_format($this->amount, 2) . ' ج.م';
                break;
            case 'delete':
                $title = 'حذف مبيعة';
                $message = 'تم حذف مبيعة بقيمة ' . number_format($this->amount, 2) . ' ج.م';
                break;
            case 'payment':
                $title = 'تحصيل مبيعة';
                $message = 'تم تغيير حالة مبيعة بقيمة ' . number_format($this->amount, 2) . ' ج.م إلى مدفوعة';
                break;
        }
        
        // إرسال الإشعار للمندوب
        return $notification->send(
            $this->salesperson_id,
            $title,
            $message,
            'sale',
            $this->id,
            'sales'
        );
    }
    
    /**
     * تغيير حالة المبيعة
     * 
     * @param int $id معرف المبيعة
     * @param string $status الحالة الجديدة
     * @return boolean نجاح أو فشل العملية
     */
    public function changeStatus($id, $status) {
        // التحقق من صحة الحالة
        $valid_statuses = ['paid', 'pending', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        // الحصول على معلومات المبيعة قبل التحديث
        $this->readOne($id);
        
        // استعلام
        $query = "UPDATE " . $this->table . "
                  SET payment_status = ?
                  WHERE id = ?";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
        // ربط المعلمات
        $stmt->bind_param("si", $status, $id);
        
        // تنفيذ الاستعلام
        if ($stmt->execute()) {
            // إذا تم تغيير الحالة إلى "مدفوعة"، قم بتحديث حالة العمولة أيضًا
            if ($status === 'paid') {
                $commission_query = "UPDATE commissions 
                                    SET status = 'paid', payment_date = CURRENT_DATE()
                                    WHERE sale_id = ? AND status = 'pending'";
                
                $commission_stmt = $this->conn->prepare($commission_query);
                $commission_stmt->bind_param("i", $id);
                $commission_stmt->execute();
                
                // إنشاء إشعار بالدفع
                $this->payment_status = $status;
                $this->createNotification('payment');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * الحصول على عدد المبيعات الكلي
     * 
     * @param array $filters مصفوفة من عوامل التصفية (اختياري)
     * @return int عدد المبيعات
     */
    public function getTotalCount($filters = []) {
        // بناء جملة الاستعلام
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " s";
        
        // إضافة شروط التصفية
        $whereClause = [];
        $params = [];
        $types = '';
        
        if (!empty($filters['client_id'])) {
            $whereClause[] = "s.client_id = ?";
            $params[] = $filters['client_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['salesperson_id'])) {
            $whereClause[] = "s.salesperson_id = ?";
            $params[] = $filters['salesperson_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['payment_status'])) {
            $whereClause[] = "s.payment_status = ?";
            $params[] = $filters['payment_status'];
            $types .= 's';
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $whereClause[] = "s.sale_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
            $types .= 'ss';
        } elseif (!empty($filters['start_date'])) {
            $whereClause[] = "s.sale_date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        } elseif (!empty($filters['end_date'])) {
            $whereClause[] = "s.sale_date <= ?";
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
     * الحصول على إحصائيات المبيعات حسب المندوب
     * 
     * @param string $period الفترة (month, year, all)
     * @return array إحصائيات المبيعات
     */
    public function getSalesBySalespersonStats($period = 'month') {
        // تحديد شرط الفترة
        $date_condition = '';
        
        switch ($period) {
            case 'month':
                $date_condition = "AND MONTH(s.sale_date) = MONTH(CURRENT_DATE()) AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())";
                break;
            case 'year':
                $date_condition = "AND YEAR(s.sale_date) = YEAR(CURRENT_DATE())";
                break;
            default:
                $date_condition = "";
        }
        
        // استعلام
        $query = "SELECT 
                    s.salesperson_id,
                    u.full_name as salesperson_name,
                    COUNT(*) as count,
                    SUM(s.amount) as amount,
                    SUM(s.commission_amount) as commission
                  FROM " . $this->table . " s
                  LEFT JOIN users u ON s.salesperson_id = u.id
                  WHERE 1=1 " . $date_condition . "
                  GROUP BY s.salesperson_id, u.full_name
                  ORDER BY amount DESC";
        
        // تحضير الاستعلام
        $stmt = $this->conn->prepare($query);
        
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
     * احتساب العمولة تلقائيًا بناءً على قيمة المبيعة ونسبة العمولة
     * 
     * @param float $amount قيمة المبيعة
     * @param float $commission_rate نسبة العمولة (اختياري، الافتراضي 2.5%)
     * @return float قيمة العمولة
     */
    public static function calculateCommission($amount, $commission_rate = 2.5) {
        return ($amount * $commission_rate) / 100;
    }
}
?>