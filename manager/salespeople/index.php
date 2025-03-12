<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومدير
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/User.php';
require_once '../../classes/Sale.php';
require_once '../../classes/Target.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$user = new User($conn);
$sale = new Sale($conn);
$target = new Target($conn);

// الحصول على معرف المستخدم من الجلسة
$user_id = $_SESSION['user_id'];

// الحصول على قائمة المندوبين التابعين للمدير
$salespeople = $user->getSalespeopleByManager($user_id);

// الحصول على الإحصائيات لكل مندوب
$sales_stats = [];
$target_stats = [];

foreach ($salespeople as $salesperson) {
    // إحصائيات المبيعات
    $salesperson_id = $salesperson['id'];
    $monthly_sales = $sale->getMonthlySales($salesperson_id, date('Y-m'));
    $sales_stats[$salesperson_id] = $monthly_sales;
    
    // إحصائيات الأهداف
    $current_target = $target->getCurrentTarget($salesperson_id);
    $target_stats[$salesperson_id] = $current_target;
}

// تعيين عنوان الصفحة
$page_title = 'إدارة المندوبين';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إدارة المندوبين</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item active">إدارة المندوبين</li>
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
    
    <!-- بطاقة المندوبين -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-users me-1"></i>
                    قائمة المندوبين
                </div>
                <div>
                    <a href="add.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i> إضافة مندوب جديد
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($salespeople) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>البريد الإلكتروني</th>
                                <th>الهاتف</th>
                                <th>المبيعات (الشهر الحالي)</th>
                                <th>تحقيق الهدف</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salespeople as $index => $salesperson) : ?>
                                <?php
                                    $salesperson_id = $salesperson['id'];
                                    $sales_amount = isset($sales_stats[$salesperson_id]) ? $sales_stats[$salesperson_id]['amount'] : 0;
                                    $target_amount = isset($target_stats[$salesperson_id]) ? $target_stats[$salesperson_id]['target_amount'] : 0;
                                    $achieved_amount = isset($target_stats[$salesperson_id]) ? $target_stats[$salesperson_id]['achieved_amount'] : 0;
                                    $percentage = calculateAchievement($achieved_amount, $target_amount);
                                    $color = getColorByPercentage($percentage);
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($salesperson['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($salesperson['email']); ?></td>
                                    <td><?php echo htmlspecialchars($salesperson['phone'] ?? 'غير متوفر'); ?></td>
                                    <td><?php echo formatMoney($sales_amount); ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo min($percentage, 100); ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $percentage; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $salesperson_id; ?>" class="btn btn-info btn-sm" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $salesperson_id; ?>" class="btn btn-primary btn-sm" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../targets/add.php?salesperson_id=<?php echo $salesperson_id; ?>" class="btn btn-warning btn-sm" title="إضافة هدف">
                                                <i class="fas fa-bullseye"></i>
                                            </a>
                                            <a href="../sales/index.php?salesperson_id=<?php echo $salesperson_id; ?>" class="btn btn-success btn-sm" title="عرض المبيعات">
                                                <i class="fas fa-shopping-cart"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا يوجد مندوبين تحت إدارتك حالياً.
                </div>
                <div class="text-center">
                    <a href="add.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> إضافة مندوب جديد
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- مخطط مقارنة المندوبين -->
    <?php if (count($salespeople) > 0) : ?>
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-chart-bar me-1"></i>
                مقارنة أداء المندوبين
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="salesComparisonChart" width="100%" height="400"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="targetComparisonChart" width="100%" height="400"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($salespeople) > 0) : ?>
    // بيانات مقارنة المبيعات بين المندوبين
    var salesCtx = document.getElementById('salesComparisonChart').getContext('2d');
    var salesChart = new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($salespeople as $salesperson) : ?>
                    '<?php echo htmlspecialchars($salesperson['full_name']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'المبيعات الشهرية (ج.م)',
                data: [
                    <?php foreach ($salespeople as $salesperson) : ?>
                        <?php echo isset($sales_stats[$salesperson['id']]) ? $sales_stats[$salesperson['id']]['amount'] : 0; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'مقارنة المبيعات الشهرية'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // بيانات مقارنة تحقيق الأهداف بين المندوبين
    var targetCtx = document.getElementById('targetComparisonChart').getContext('2d');
    var targetChart = new Chart(targetCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($salespeople as $salesperson) : ?>
                    '<?php echo htmlspecialchars($salesperson['full_name']); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'نسبة تحقيق الهدف (%)',
                data: [
                    <?php foreach ($salespeople as $salesperson) : ?>
                        <?php 
                            $salesperson_id = $salesperson['id'];
                            $target_amount = isset($target_stats[$salesperson_id]) ? $target_stats[$salesperson_id]['target_amount'] : 0;
                            $achieved_amount = isset($target_stats[$salesperson_id]) ? $target_stats[$salesperson_id]['achieved_amount'] : 0;
                            echo calculateAchievement($achieved_amount, $target_amount);
                        ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'مقارنة نسب تحقيق الأهداف'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>