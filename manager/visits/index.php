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
require_once '../../classes/Visit.php';
require_once '../../classes/User.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$visit = new Visit($conn);
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

// فلترة حسب الحالة
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['visit_status'] = $_GET['status'];
}

// فلترة حسب التاريخ
if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}

// البحث
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// الحصول على قائمة الزيارات
$visits_list = [];

// إذا تم تحديد مندوب معين
if (isset($filters['salesperson_id'])) {
    // الحصول على زيارات مندوب محدد
    $visits_list = $visit->readAll($filters);
} else {
    // جمع زيارات جميع المندوبين التابعين للمدير
    foreach ($salespeople as $sp) {
        $sp_filters = $filters;
        $sp_filters['salesperson_id'] = $sp['id'];
        $sp_visits = $visit->readAll($sp_filters);
        $visits_list = array_merge($visits_list, $sp_visits);
    }
}

// فرز الزيارات حسب الوقت (من الأحدث إلى الأقدم)
usort($visits_list, function($a, $b) {
    return strtotime($b['visit_time']) - strtotime($a['visit_time']);
});

// حساب إحصائيات الزيارات
$total_visits = count($visits_list);
$planned_visits = 0;
$completed_visits = 0;
$cancelled_visits = 0;

foreach ($visits_list as $v) {
    if ($v['visit_status'] === 'planned') {
        $planned_visits++;
    } elseif ($v['visit_status'] === 'completed') {
        $completed_visits++;
    } elseif ($v['visit_status'] === 'cancelled') {
        $cancelled_visits++;
    }
}

// تعيين عنوان الصفحة
$page_title = 'متابعة الزيارات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">متابعة الزيارات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">متابعة الزيارات</li>
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
    
    <!-- ملخص الزيارات -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $total_visits; ?></h4>
                            <div class="small">إجمالي الزيارات</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar-check fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php">عرض التفاصيل</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $planned_visits; ?></h4>
                            <div class="small">الزيارات المخططة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-calendar fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=planned">عرض المخططة</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $completed_visits; ?></h4>
                            <div class="small">الزيارات المكتملة</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=completed">عرض المكتملة</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo $cancelled_visits; ?></h4>
                            <div class="small">الزيارات الملغية</div>
                        </div>
                        <div class="ms-2">
                            <i class="fas fa-times-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <a class="small text-white stretched-link" href="index.php?status=cancelled">عرض الملغية</a>
                    <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة الفلاتر -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            فلترة الزيارات
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-3 mb-3">
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
                    <div class="col-md-3 mb-3">
                        <label for="status" class="form-label">حالة الزيارة</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">الكل</option>
                            <option value="planned" <?php echo (isset($_GET['status']) && $_GET['status'] === 'planned') ? 'selected' : ''; ?>>مخططة</option>
                            <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="start_date" class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="end_date" class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-9 mb-3">
                        <label for="search" class="form-label">بحث</label>
                        <input type="text" class="form-control" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="اسم الشركة، العميل...">
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> بحث
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- بطاقة قائمة الزيارات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-check me-1"></i>
            قائمة الزيارات
        </div>
        <div class="card-body">
            <?php if (count($visits_list) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th>المندوب</th>
                                <th>الشركة</th>
                                <th>العميل</th>
                                <th>الهاتف</th>
                                <th>تاريخ الزيارة</th>
                                <th>الغرض</th>
                                <th>الحالة</th>
                                <th width="10%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits_list as $index => $v) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($v['salesperson_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($v['client_phone']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($v['visit_time'])); ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($v['purpose'], 0, 30)) . (mb_strlen($v['purpose']) > 30 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($v['visit_status'] === 'planned') : ?>
                                            <span class="badge visit-planned">مخططة</span>
                                        <?php elseif ($v['visit_status'] === 'completed') : ?>
                                            <span class="badge visit-completed">مكتملة</span>
                                        <?php else : ?>
                                            <span class="badge visit-cancelled">ملغية</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $v['id']; ?>" class="btn btn-info btn-sm" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد زيارات متاحة وفقًا للفلاتر المحددة.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- إحصائيات الزيارات -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-chart-bar me-1"></i>
            إحصائيات الزيارات
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <!-- رسم بياني لحالات الزيارات -->
                    <canvas id="visitStatusChart" width="100%" height="50"></canvas>
                </div>
                <div class="col-lg-6">
                    <!-- رسم بياني لتوزيع الزيارات على المندوبين -->
                    <canvas id="visitsBySalespersonChart" width="100%" height="50"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript للرسوم البيانية -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // رسم بياني لحالات الزيارات
    var statusCtx = document.getElementById('visitStatusChart').getContext('2d');
    var statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['مخططة', 'مكتملة', 'ملغية'],
            datasets: [{
                data: [<?php echo $planned_visits; ?>, <?php echo $completed_visits; ?>, <?php echo $cancelled_visits; ?>],
                backgroundColor: [
                    'rgba(0, 123, 255, 0.7)',
                    'rgba(40, 167, 69, 0.7)',
                    'rgba(220, 53, 69, 0.7)'
                ],
                borderColor: [
                    'rgba(0, 123, 255, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع الزيارات حسب الحالة'
                }
            }
        }
    });
    
    // جمع إحصائيات الزيارات حسب المندوب
    <?php
        // إعداد المصفوفات لتخزين بيانات المندوبين والإحصائيات
        $salespeople_names = [];
        $salespeople_visit_counts = [];
        
        // حساب عدد الزيارات لكل مندوب
        $visits_by_salesperson = [];
        foreach ($salespeople as $sp) {
            $visits_by_salesperson[$sp['id']] = [
                'name' => $sp['full_name'],
                'count' => 0
            ];
        }
        
        foreach ($visits_list as $v) {
            if (isset($visits_by_salesperson[$v['salesperson_id']])) {
                $visits_by_salesperson[$v['salesperson_id']]['count']++;
            }
        }
        
        // تجهيز البيانات للرسم البياني
        foreach ($visits_by_salesperson as $sp) {
            $salespeople_names[] = $sp['name'];
            $salespeople_visit_counts[] = $sp['count'];
        }
    ?>
    
    // رسم بياني لتوزيع الزيارات على المندوبين
    var salespersonCtx = document.getElementById('visitsBySalespersonChart').getContext('2d');
    var salespersonChart = new Chart(salespersonCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($salespeople_names); ?>,
            datasets: [{
                label: 'عدد الزيارات',
                data: <?php echo json_encode($salespeople_visit_counts); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'توزيع الزيارات حسب المندوب'
                }
            }
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>