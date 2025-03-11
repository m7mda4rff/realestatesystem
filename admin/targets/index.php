<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Target.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$user = new User($conn);

// التحقق من عمليات الحذف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    // التحقق من وجود الهدف وحذفه
    $target->id = $target_id;
    if ($target->delete()) {
        $success_message = 'تم حذف الهدف بنجاح';
    } else {
        $error_message = 'حدث خطأ أثناء حذف الهدف';
    }
}

// إعدادات الفلترة
$filters = [];

// فلترة حسب المندوب
if (isset($_GET['salesperson']) && !empty($_GET['salesperson'])) {
    $filters['salesperson_id'] = (int)$_GET['salesperson'];
}

// فلترة حسب التاريخ
if (isset($_GET['date_range'])) {
    switch ($_GET['date_range']) {
        case 'current':
            $filters['current'] = true;
            break;
        case 'past':
            $filters['past'] = true;
            break;
        case 'future':
            $filters['future'] = true;
            break;
    }
}

// البحث
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// الحصول على قائمة الأهداف
// يجب تعديل دالة readAll في كلاس Target للتعامل مع الفلاتر
$targets = [];

// إذا لم تكن هناك فلاتر، نحصل على جميع الأهداف
if (empty($filters)) {
    // نفترض أن هناك دالة getAllTargets في كلاس Target
    $query = "SELECT t.*, u.full_name as salesperson_name, c.full_name as created_by_name
              FROM targets t
              LEFT JOIN users u ON t.salesperson_id = u.id
              LEFT JOIN users c ON t.created_by = c.id
              ORDER BY t.end_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $targets[] = $row;
    }
} else {
    // بناء الاستعلام مع الفلاتر
    $query = "SELECT t.*, u.full_name as salesperson_name, c.full_name as created_by_name
              FROM targets t
              LEFT JOIN users u ON t.salesperson_id = u.id
              LEFT JOIN users c ON t.created_by = c.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (isset($filters['salesperson_id'])) {
        $query .= " AND t.salesperson_id = ?";
        $params[] = $filters['salesperson_id'];
        $types .= "i";
    }
    
    if (isset($filters['current'])) {
        $query .= " AND CURRENT_DATE() BETWEEN t.start_date AND t.end_date";
    } elseif (isset($filters['past'])) {
        $query .= " AND t.end_date < CURRENT_DATE()";
    } elseif (isset($filters['future'])) {
        $query .= " AND t.start_date > CURRENT_DATE()";
    }
    
    if (isset($filters['search'])) {
        $search_term = "%" . $filters['search'] . "%";
        $query .= " AND (u.full_name LIKE ? OR t.notes LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    
    $query .= " ORDER BY t.end_date DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $targets[] = $row;
    }
}

// الحصول على قائمة مندوبي المبيعات للفلترة
$salespeople = $user->readAll(['role' => 'salesperson']);

// تعيين عنوان الصفحة
$page_title = 'إدارة الأهداف';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة الأهداف</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة الأهداف</li>
    </ol>
    
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($success_message)) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة الأهداف
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="salesperson" class="form-label">مندوب المبيعات</label>
                        <select class="form-select" id="salesperson" name="salesperson">
                            <option value="">الكل</option>
                            <?php foreach ($salespeople as $person) : ?>
                                <option value="<?php echo $person['id']; ?>" <?php echo (isset($_GET['salesperson']) && $_GET['salesperson'] == $person['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($person['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_range" class="form-label">الفترة الزمنية</label>
                        <select class="form-select" id="date_range" name="date_range">
                            <option value="" <?php echo !isset($_GET['date_range']) ? 'selected' : ''; ?>>الكل</option>
                            <option value="current" <?php echo (isset($_GET['date_range']) && $_GET['date_range'] === 'current') ? 'selected' : ''; ?>>الحالية</option>
                            <option value="past" <?php echo (isset($_GET['date_range']) && $_GET['date_range'] === 'past') ? 'selected' : ''; ?>>السابقة</option>
                            <option value="future" <?php echo (isset($_GET['date_range']) && $_GET['date_range'] === 'future') ? 'selected' : ''; ?>>المستقبلية</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="search" class="form-label">بحث</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="ابحث بالاسم أو الملاحظات...">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> بحث
                        </button>
                        <a href="index.php" class="btn btn-secondary me-2">
                            <i class="fas fa-redo me-1"></i> إعادة تعيين
                        </a>
                        <a href="add.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> إضافة هدف جديد
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- بطاقة الأهداف -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-bullseye me-1"></i>
            قائمة الأهداف
        </div>
        <div class="card-body">
            <?php if (count($targets) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>المندوب</th>
                                <th>الهدف</th>
                                <th>المحقق</th>
                                <th>نسبة الإنجاز</th>
                                <th>الفترة</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($targets as $index => $target_item) : ?>
                                <?php
                                    // حساب نسبة تحقيق الهدف
                                    $achievement_percentage = calculateAchievement($target_item['achieved_amount'], $target_item['target_amount']);
                                    
                                    // تحديد لون شريط التقدم بناءً على النسبة
                                    $progress_color = getColorByPercentage($achievement_percentage);
                                    
                                    // تحديد حالة الهدف (حالي، سابق، مستقبلي)
                                    $today = date('Y-m-d');
                                    $target_status = '';
                                    if ($today >= $target_item['start_date'] && $today <= $target_item['end_date']) {
                                        $target_status = 'current';
                                    } elseif ($today > $target_item['end_date']) {
                                        $target_status = 'past';
                                    } else {
                                        $target_status = 'future';
                                    }
                                ?>
                                <tr class="<?php echo $target_status === 'current' ? 'table-info' : ($target_status === 'past' ? 'table-secondary' : ''); ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($target_item['salesperson_name']); ?></td>
                                    <td><?php echo formatMoney($target_item['target_amount']); ?></td>
                                    <td><?php echo formatMoney($target_item['achieved_amount']); ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $progress_color; ?>" role="progressbar" style="width: <?php echo min($achievement_percentage, 100); ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $achievement_percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            echo date('Y-m-d', strtotime($target_item['start_date'])) . ' إلى ' . date('Y-m-d', strtotime($target_item['end_date'])); 
                                            if ($target_status === 'current') {
                                                echo ' <span class="badge bg-info">حالي</span>';
                                            } elseif ($target_status === 'past') {
                                                echo ' <span class="badge bg-secondary">منتهي</span>';
                                            } else {
                                                echo ' <span class="badge bg-warning">مستقبلي</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $target_item['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $target_item['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="index.php?action=delete&id=<?php echo $target_item['id']; ?>" class="btn btn-danger btn-sm btn-delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا الهدف؟');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد أهداف متاحة.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>