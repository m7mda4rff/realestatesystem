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
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$target = new Target($conn);
$user = new User($conn);
$sale = new Sale($conn);

// الحصول على معرف المستخدم من الجلسة
$manager_id = $_SESSION['user_id'];

// التحقق من وجود معرف الهدف
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف الهدف غير صحيح';
    header('Location: index.php');
    exit;
}

$target_id = (int)$_GET['id'];

// قراءة بيانات الهدف الحالي
if (!$target->readOne($target_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على الهدف المطلوب';
    header('Location: index.php');
    exit;
}

// الحصول على قائمة مندوبي المبيعات التابعين للمدير
$salespeople = $user->getSalespeopleByManager($manager_id);

// التحقق من أن المندوب يتبع للمدير الحالي
$belongs_to_manager = false;
foreach ($salespeople as $sp) {
    if ($sp['id'] === $target->salesperson_id) {
        $belongs_to_manager = true;
        $salesperson_name = $sp['full_name'];
        break;
    }
}

if (!$belongs_to_manager) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية عرض هذا الهدف';
    header('Location: index.php');
    exit;
}

// حساب نسبة تحقيق الهدف
$achievement_percentage = calculateAchievement($target->achieved_amount, $target->target_amount);
$status_color = getColorByPercentage($achievement_percentage);

// الحصول على المبيعات المرتبطة بالهدف (في نفس الفترة للمندوب)
$sales_filters = [
    'salesperson_id' => $target->salesperson_id,
    'start_date' => $target->start_date,
    'end_date' => $target->end_date
];
$related_sales = $sale->readAll($sales_filters);

// تحديد حالة الهدف (حالي، سابق، مستقبلي)
$current_date = date('Y-m-d');
$is_current = ($target->start_date <= $current_date && $target->end_date >= $current_date);
$is_past = ($target->end_date < $current_date);
$is_future = ($target->start_date > $current_date);

if ($is_current) {
    $status_text = 'هدف حالي';
    $status_badge = 'primary';
} elseif ($is_past) {
    $status_text = 'هدف سابق';
    $status_badge = 'secondary';
} else {
    $status_text = 'هدف مستقبلي';
    $status_badge = 'info';
}

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل الهدف';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل الهدف</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة الأهداف</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل الهدف</li>
    </ol>
    
    <div class="row">
        <!-- بطاقة معلومات الهدف -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-bullseye me-1"></i>
                            معلومات الهدف
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">المندوب</th>
                                    <td><?php echo htmlspecialchars($salesperson_name); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">المبلغ المستهدف</th>
                                    <td><?php echo formatMoney($target->target_amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">المبلغ المحقق</th>
                                    <td><?php echo formatMoney($target->achieved_amount); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">نسبة التحقيق</th>
                                    <td><span class="badge bg-<?php echo $status_color; ?>"><?php echo $achievement_percentage; ?>%</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-muted" width="40%">تاريخ البداية</th>
                                    <td><?php echo date('Y-m-d', strtotime($target->start_date)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ النهاية</th>
                                    <td><?php echo date('Y-m-d', strtotime($target->end_date)); ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">مدة الهدف</th>
                                    <td>
                                        <?php
                                            $start = new DateTime($target->start_date);
                                            $end = new DateTime($target->end_date);
                                            $interval = $start->diff($end);
                                            echo $interval->format('%a يوم');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted">تاريخ الإنشاء</th>
                                    <td><?php echo date('Y-m-d', strtotime($target->created_at)); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3">تقدم تحقيق الهدف</h5>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo min($achievement_percentage, 100); ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $achievement_percentage; ?>%
                        </div>
                    </div>
                    
                    <?php if (!empty($target->notes)) : ?>
                        <h5 class="mt-4 mb-3">ملاحظات</h5>
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($target->notes)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- بطاقة المبيعات المرتبطة -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-shopping-cart me-1"></i>
                    المبيعات المرتبطة بالهدف
                </div>
                <div class="card-body">
                    <?php if (count($related_sales) > 0) : ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>العميل</th>
                                        <th>تاريخ البيع</th>
                                        <th>المبلغ</th>
                                        <th>العمولة</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($related_sales as $index => $sale_item) : ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($sale_item['client_name']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($sale_item['sale_date'])); ?></td>
                                            <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                            <td><?php echo formatMoney($sale_item['commission_amount']); ?></td>
                                            <td>
                                                <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                                    <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="3">الإجمالي</th>
                                        <th>
                                            <?php
                                                $total_amount = array_reduce($related_sales, function($carry, $item) {
                                                    return $carry + $item['amount'];
                                                }, 0);
                                                echo formatMoney($total_amount);
                                            ?>
                                        </th>
                                        <th>
                                            <?php
                                                $total_commission = array_reduce($related_sales, function($carry, $item) {
                                                    return $carry + $item['commission_amount'];
                                                }, 0);
                                                echo formatMoney($total_commission);
                                            ?>
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات مرتبطة بهذا الهدف حتى الآن.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- بطاقة الإحصائيات والإجراءات -->
        <div class="col-lg-4">
            <!-- بطاقة الإجراءات -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-cogs me-1"></i>
                    الإجراءات المتاحة
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $target_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> تعديل الهدف
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> العودة إلى القائمة
                        </a>
                        <a href="index.php?action=delete&id=<?php echo $target_id; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الهدف؟');">
                            <i class="fas fa-trash me-1"></i> حذف الهدف
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- بطاقة إحصائيات إضافية -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    إحصائيات إضافية
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>وضع تحقيق الهدف</h6>
                        <?php
                            // حساب النسبة المتوقعة للتحقيق بناءً على الوقت المنقضي
                            $start = new DateTime($target->start_date);
                            $end = new DateTime($target->end_date);
                            $now = new DateTime();
                            
                            // تجاوز فترة الهدف
                            if ($now > $end) {
                                $time_progress = 100;
                            } 
                            // لم تبدأ فترة الهدف بعد
                            elseif ($now < $start) {
                                $time_progress = 0;
                            } 
                            // في منتصف فترة الهدف
                            else {
                                $total_days = $start->diff($end)->days;
                                $elapsed_days = $start->diff($now)->days;
                                $time_progress = ($total_days > 0) ? ($elapsed_days / $total_days) * 100 : 0;
                            }
                            
                            // مقارنة التقدم الفعلي مع المتوقع
                            $achievement_ratio = ($time_progress > 0) ? ($achievement_percentage / $time_progress) : 0;
                            
                            if ($achievement_ratio >= 1.1) {
                                $performance_status = 'ممتاز';
                                $performance_color = 'success';
                            } elseif ($achievement_ratio >= 0.9) {
                                $performance_status = 'جيد';
                                $performance_color = 'info';
                            } elseif ($achievement_ratio >= 0.7) {
                                $performance_status = 'مقبول';
                                $performance_color = 'warning';
                            } else {
                                $performance_status = 'ضعيف';
                                $performance_color = 'danger';
                            }
                            
                            // حساب المتبقي لتحقيق الهدف
                            $remaining_amount = max(0, $target->target_amount - $target->achieved_amount);
                            
                            // حساب المتوسط اليومي المطلوب لتحقيق الهدف
                            $days_left = max(0, (new DateTime($target->end_date))->diff(new DateTime())->days);
                            $daily_required = ($days_left > 0 && $remaining_amount > 0) ? $remaining_amount / $days_left : 0;
                        ?>
                        
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-grow-1 me-3">
                                <div class="small">تقدم الوقت: <?php echo round($time_progress); ?>%</div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $time_progress; ?>%;" aria-valuenow="<?php echo $time_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small">تقدم الإنجاز: <?php echo $achievement_percentage; ?>%</div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $status_color; ?>" role="progressbar" style="width: <?php echo $achievement_percentage; ?>%;" aria-valuenow="<?php echo $achievement_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-<?php echo $performance_color; ?> mb-0">
                            <strong>الأداء العام: <?php echo $performance_status; ?></strong>
                            <?php if ($performance_status === 'ضعيف' || $performance_status === 'مقبول') : ?>
                                <p class="mb-0 small">يجب زيادة المبيعات للوصول إلى الهدف.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-0">
                        <h6>المتبقي لتحقيق الهدف</h6>
                        <div class="h4"><?php echo formatMoney($remaining_amount); ?></div>
                        
                        <?php if ($days_left > 0 && $remaining_amount > 0) : ?>
                            <div class="small text-muted">
                                المتوسط اليومي المطلوب: <?php echo formatMoney($daily_required); ?>
                                <div class="small">المدة المتبقية: <?php echo $days_left; ?> يوم</div>
                            </div>
                        <?php elseif ($remaining_amount <= 0) : ?>
                            <div class="text-success">تم تحقيق الهدف بالكامل!</div>
                        <?php else : ?>
                            <div class="text-danger">انتهت فترة الهدف ولم يتم تحقيقه بالكامل.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- بطاقة التوزيع الشهري للمبيعات -->
            <?php if (count($related_sales) > 0) : ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        التوزيع الشهري للمبيعات
                    </div>
                    <div class="card-body">
                        <canvas id="monthlySalesChart" width="100%" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (count($related_sales) > 0) : ?>
<!-- JavaScript للرسوم البيانية -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // جمع البيانات حسب الشهر
    var salesByMonth = {};
    
    <?php
        // تجميع المبيعات حسب الشهر
        $sales_by_month = [];
        foreach ($related_sales as $sale_item) {
            $month = date('Y-m', strtotime($sale_item['sale_date']));
            if (!isset($sales_by_month[$month])) {
                $sales_by_month[$month] = 0;
            }
            $sales_by_month[$month] += $sale_item['amount'];
        }
        
        // ترتيب المبيعات حسب الشهر
        ksort($sales_by_month);
        
        // عرض البيانات للجافاسكريبت
        foreach ($sales_by_month as $month => $amount) {
            echo "salesByMonth['{$month}'] = {$amount};\n";
        }
    ?>
    
    // تحويل البيانات إلى مصفوفات للرسم البياني
    var months = Object.keys(salesByMonth);
    var amounts = months.map(function(month) {
        return salesByMonth[month];
    });
    
    // تهيئة الرسم البياني
    var ctx = document.getElementById('monthlySalesChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months.map(function(month) {
                // تحويل التاريخ من YYYY-MM إلى اسم الشهر والسنة
                var date = new Date(month + '-01');
                return new Intl.DateTimeFormat('ar', { month: 'short', year: 'numeric' }).format(date);
            }),
            datasets: [{
                label: 'المبيعات الشهرية',
                data: amounts,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'المبلغ (ج.م)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'الشهر'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'المبيعات الشهرية خلال فترة الهدف'
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>