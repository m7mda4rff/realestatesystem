<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
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

// الحصول على معرف المستخدم من الجلسة
$manager_id = $_SESSION['user_id'];

// الحصول على قائمة مندوبي المبيعات التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);

// إعدادات الفلترة
$filters = [];

// فلترة حسب المندوب
if (isset($_GET['salesperson_id']) && !empty($_GET['salesperson_id'])) {
    $filters['salesperson_id'] = (int)$_GET['salesperson_id'];
}

// فلترة حسب الفترة الزمنية
if (isset($_GET['period']) && !empty($_GET['period'])) {
    $period = $_GET['period'];
    
    switch ($period) {
        case 'current':
            $filters['is_current'] = true;
            break;
        case 'past':
            $filters['is_past'] = true;
            break;
        case 'future':
            $filters['is_future'] = true;
            break;
    }
}

// عملية حذف هدف
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    
    // التحقق من أن الهدف يخص أحد مندوبي المدير
    $target->readOne($target_id);
    $salesperson_belongs_to_manager = false;
    
    foreach ($salespeople as $sp) {
        if ($sp['id'] === $target->salesperson_id) {
            $salesperson_belongs_to_manager = true;
            break;
        }
    }
    
    if ($salesperson_belongs_to_manager) {
        // محاولة حذف الهدف
        $target->id = $target_id;
        if ($target->delete()) {
            $_SESSION['success_message'] = 'تم حذف الهدف بنجاح';
        } else {
            $_SESSION['error_message'] = 'حدث خطأ أثناء حذف الهدف';
        }
    } else {
        $_SESSION['error_message'] = 'ليس لديك صلاحية حذف هذا الهدف';
    }
    
    // إعادة توجيه لتجنب إعادة تنفيذ العملية عند تحديث الصفحة
    header('Location: index.php');
    exit;
}

// الحصول على قائمة الأهداف
$targets_list = [];

// إذا تم تحديد مندوب معين
if (isset($filters['salesperson_id'])) {
    // الحصول على جميع أهداف المندوب المحدد
    $targets_list = $target->getTargetsBySalesperson($filters['salesperson_id']);
} else {
    // جمع أهداف جميع المندوبين التابعين للمدير
    foreach ($salespeople as $sp) {
        $sp_targets = $target->getTargetsBySalesperson($sp['id']);
        $targets_list = array_merge($targets_list, $sp_targets);
    }
}

// تطبيق فلترة الفترة الزمنية
if (isset($filters['is_current']) || isset($filters['is_past']) || isset($filters['is_future'])) {
    $filtered_targets = [];
    $current_date = date('Y-m-d');
    
    foreach ($targets_list as $t) {
        // تحديد نوع الهدف (حالي، سابق، مستقبلي)
        $is_current = ($t['start_date'] <= $current_date && $t['end_date'] >= $current_date);
        $is_past = ($t['end_date'] < $current_date);
        $is_future = ($t['start_date'] > $current_date);
        
        // تطبيق الفلتر
        if ((isset($filters['is_current']) && $is_current) ||
            (isset($filters['is_past']) && $is_past) ||
            (isset($filters['is_future']) && $is_future)) {
            $filtered_targets[] = $t;
        }
    }
    
    $targets_list = $filtered_targets;
}

// فرز الأهداف حسب تاريخ البدء (من الأحدث إلى الأقدم)
usort($targets_list, function($a, $b) {
    return strtotime($b['start_date']) - strtotime($a['start_date']);
});

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
    <?php if (isset($_SESSION['success_message'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- بطاقة فلتر الأهداف -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة الأهداف
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label for="salesperson_id" class="form-label">مندوب المبيعات</label>
                        <select class="form-select select2" id="salesperson_id" name="salesperson_id">
                            <option value="">جميع المندوبين</option>
                            <?php foreach ($salespeople as $sp) : ?>
                                <option value="<?php echo $sp['id']; ?>" <?php echo (isset($_GET['salesperson_id']) && $_GET['salesperson_id'] == $sp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sp['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label for="period" class="form-label">الفترة الزمنية</label>
                        <select class="form-select" id="period" name="period">
                            <option value="">جميع الفترات</option>
                            <option value="current" <?php echo (isset($_GET['period']) && $_GET['period'] === 'current') ? 'selected' : ''; ?>>الأهداف الحالية</option>
                            <option value="past" <?php echo (isset($_GET['period']) && $_GET['period'] === 'past') ? 'selected' : ''; ?>>الأهداف السابقة</option>
                            <option value="future" <?php echo (isset($_GET['period']) && $_GET['period'] === 'future') ? 'selected' : ''; ?>>الأهداف المستقبلية</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> عرض
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- بطاقة قائمة الأهداف -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-bullseye me-1"></i>
                قائمة الأهداف
            </div>
            <div>
                <a href="add.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus me-1"></i> إضافة هدف جديد
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($targets_list) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>المندوب</th>
                                <th>المبلغ المستهدف</th>
                                <th>المبلغ المحقق</th>
                                <th>نسبة التحقيق</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ النهاية</th>
                                <th>الحالة</th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($targets_list as $index => $t) : 
                                // الحصول على اسم المندوب
                                $salesperson_name = '';
                                foreach ($salespeople as $sp) {
                                    if ($sp['id'] == $t['salesperson_id']) {
                                        $salesperson_name = $sp['full_name'];
                                        break;
                                    }
                                }
                                
                                // حساب نسبة تحقيق الهدف
                                $achievement_percentage = calculateAchievement($t['achieved_amount'], $t['target_amount']);
                                $status_color = getColorByPercentage($achievement_percentage);
                                
                                // تحديد حالة الهدف
                                $current_date = date('Y-m-d');
                                $is_current = ($t['start_date'] <= $current_date && $t['end_date'] >= $current_date);
                                $is_past = ($t['end_date'] < $current_date);
                                $is_future = ($t['start_date'] > $current_date);
                                
                                if ($is_current) {
                                    $status_text = 'حالي';
                                    $status_badge = 'primary';
                                } elseif ($is_past) {
                                    $status_text = 'سابق';
                                    $status_badge = 'secondary';
                                } else {
                                    $status_text = 'مستقبلي';
                                    $status_badge = 'info';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($salesperson_name); ?></td>
                                    <td><?php echo formatMoney($t['target_amount']); ?></td>
                                    <td><?php echo formatMoney($t['achieved_amount']); ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo min($achievement_percentage, 100); ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $achievement_percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($t['start_date'])); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($t['end_date'])); ?></td>
                                    <td><span class="badge bg-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <a href="view.php?id=<?php echo $t['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="index.php?action=delete&id=<?php echo $t['id']; ?>" class="btn btn-danger btn-sm btn-delete" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا الهدف؟');">
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
                    <i class="fas fa-info-circle me-1"></i> لا توجد أهداف متاحة وفقًا للفلاتر المحددة.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ملخص أداء المندوبين -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-bar me-1"></i>
            ملخص أداء المندوبين (الأهداف الحالية)
        </div>
        <div class="card-body">
            <?php
                // جمع بيانات الأهداف الحالية
                $current_targets = [];
                $current_date = date('Y-m-d');
                
                foreach ($salespeople as $sp) {
                    $has_current_target = false;
                    
                    foreach ($targets_list as $t) {
                        if ($t['salesperson_id'] == $sp['id'] && $t['start_date'] <= $current_date && $t['end_date'] >= $current_date) {
                            $current_targets[] = [
                                'salesperson_id' => $sp['id'],
                                'salesperson_name' => $sp['full_name'],
                                'target_amount' => $t['target_amount'],
                                'achieved_amount' => $t['achieved_amount'],
                                'achievement_percentage' => calculateAchievement($t['achieved_amount'], $t['target_amount']),
                                'start_date' => $t['start_date'],
                                'end_date' => $t['end_date']
                            ];
                            $has_current_target = true;
                            break;
                        }
                    }
                    
                    // إذا لم يكن لدى المندوب هدف حالي
                    if (!$has_current_target) {
                        $current_targets[] = [
                            'salesperson_id' => $sp['id'],
                            'salesperson_name' => $sp['full_name'],
                            'target_amount' => 0,
                            'achieved_amount' => 0,
                            'achievement_percentage' => 0,
                            'start_date' => '',
                            'end_date' => ''
                        ];
                    }
                }
                
                // ترتيب المندوبين حسب نسبة التحقيق (من الأعلى إلى الأقل)
                usort($current_targets, function($a, $b) {
                    return $b['achievement_percentage'] - $a['achievement_percentage'];
                });
            ?>
            
            <?php if (count($current_targets) > 0) : ?>
                <div class="row">
                    <div class="col-lg-8">
                        <!-- عرض رسم بياني لنسب تحقيق الأهداف -->
                        <canvas id="salespeopleTrend" width="100%" height="50"></canvas>
                    </div>
                    <div class="col-lg-4">
                        <!-- عرض قائمة بأداء المندوبين -->
                        <h5 class="mb-3">ترتيب المندوبين حسب نسبة تحقيق الأهداف</h5>
                        <div class="list-group">
                            <?php foreach ($current_targets as $index => $ct) : 
                                $status_color = getColorByPercentage($ct['achievement_percentage']);
                            ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo ($index + 1) . '. ' . htmlspecialchars($ct['salesperson_name']); ?>
                                        </h6>
                                        <span class="badge bg-<?php echo $status_color; ?>"><?php echo $ct['achievement_percentage']; ?>%</span>
                                    </div>
                                    <div class="progress mt-2" style="height: 10px;">
                                        <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo min($ct['achievement_percentage'], 100); ?>%;" aria-valuenow="<?php echo $ct['achievement_percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo formatMoney($ct['achieved_amount']); ?> من <?php echo formatMoney($ct['target_amount']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد أهداف حالية للمندوبين.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- إضافة JavaScript لإنشاء الرسم البياني -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // الرسم البياني لأداء المندوبين
    var ctx = document.getElementById('salespeopleTrend');
    if (ctx) {
        var myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($current_targets as $ct): ?>
                        '<?php echo htmlspecialchars($ct['salesperson_name']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'نسبة تحقيق الهدف (%)',
                    data: [
                        <?php foreach ($current_targets as $ct): ?>
                            <?php echo $ct['achievement_percentage']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        <?php foreach ($current_targets as $ct): ?>
                            '<?php echo "rgba(" . rand(50, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 0.7)"; ?>',
                        <?php endforeach; ?>
                    ],
                    borderColor: [
                        <?php foreach ($current_targets as $ct): ?>
                            '<?php echo "rgba(" . rand(50, 200) . ", " . rand(50, 200) . ", " . rand(50, 200) . ", 1)"; ?>',
                        <?php endforeach; ?>
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'نسبة التحقيق (%)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'المندوبين'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'نسبة تحقيق الأهداف للمندوبين'
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>