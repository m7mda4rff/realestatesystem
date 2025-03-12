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
require_once '../../classes/Client.php';
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$client = new Client($conn);
$sale = new Sale($conn);

// التحقق من وجود معرف العميل في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// الحصول على معرف العميل
$client_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل العميل
if (!$client->readOne($client_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على العميل المطلوب';
    header('Location: index.php');
    exit;
}

// الحصول على مبيعات العميل
$client_sales = $sale->readAll(['client_id' => $client_id]);

// تعيين عنوان الصفحة
$page_title = 'عرض تفاصيل العميل';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض تفاصيل العميل</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة العملاء</a></li>
        <li class="breadcrumb-item active">عرض تفاصيل العميل</li>
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
    
    <div class="row">
        <!-- بطاقة تفاصيل العميل -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-user me-1"></i>
                        تفاصيل العميل
                    </div>
                    <div>
                        <a href="edit.php?id=<?php echo $client_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i> تعديل
                        </a>
                        <a href="#" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash me-1"></i> حذف
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                        <h3><?php echo htmlspecialchars($client->name); ?></h3>
                    </div>
                    
                    <table class="table table-striped">
                        <tr>
                            <th style="width: 30%;">رقم الهاتف</th>
                            <td>
                                <?php if (!empty($client->phone)) : ?>
                                    <a href="tel:<?php echo htmlspecialchars($client->phone); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($client->phone); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="text-muted">غير محدد</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>البريد الإلكتروني</th>
                            <td>
                                <?php if (!empty($client->email)) : ?>
                                    <a href="mailto:<?php echo htmlspecialchars($client->email); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($client->email); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="text-muted">غير محدد</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>العنوان</th>
                            <td>
                                <?php echo !empty($client->address) ? htmlspecialchars($client->address) : '<span class="text-muted">غير محدد</span>'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ الإضافة</th>
                            <td><?php echo formatDate($client->created_at); ?></td>
                        </tr>
                        <tr>
                            <th>إجمالي المبيعات</th>
                            <td>
                                <?php
                                    $total_sales = 0;
                                    foreach ($client_sales as $sale_item) {
                                        $total_sales += $sale_item['amount'];
                                    }
                                    echo formatMoney($total_sales);
                                ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($client->notes)) : ?>
                        <div class="mt-4">
                            <h5>ملاحظات</h5>
                            <div class="p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($client->notes)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-center gap-2">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> العودة للقائمة
                        </a>
                        <?php if (!empty($client->email)) : ?>
                            <a href="mailto:<?php echo htmlspecialchars($client->email); ?>" class="btn btn-info">
                                <i class="fas fa-envelope me-1"></i> مراسلة
                            </a>
                        <?php endif; ?>
                        <a href="../sales/add.php?client_id=<?php echo $client_id; ?>" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> إضافة مبيعة
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- بطاقة إحصائيات العميل -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    إحصائيات العميل
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-primary text-white mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">عدد المبيعات</div>
                                            <div class="h3"><?php echo count($client_sales); ?></div>
                                        </div>
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-success text-white mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="small">إجمالي المبيعات</div>
                                            <div class="h3"><?php echo formatMoney($total_sales); ?></div>
                                        </div>
                                        <i class="fas fa-money-bill fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($client_sales) > 0) : ?>
                        <canvas id="salesChart" height="200"></canvas>
                        <script>
                            // سيتم إضافة الرسم البياني باستخدام JavaScript
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('salesChart').getContext('2d');
                                const salesData = {
                                    labels: [
                                        <?php
                                            // الحصول على آخر 6 مبيعات أو أقل حسب ما هو متاح
                                            $recent_sales = array_slice($client_sales, 0, 6);
                                            $labels = [];
                                            $data = [];
                                            foreach (array_reverse($recent_sales) as $sale_item) {
                                                $labels[] = "'" . date('Y-m-d', strtotime($sale_item['sale_date'])) . "'";
                                                $data[] = $sale_item['amount'];
                                            }
                                            echo implode(',', $labels);
                                        ?>
                                    ],
                                    datasets: [{
                                        label: 'قيمة المبيعات',
                                        data: [
                                            <?php echo implode(',', $data); ?>
                                        ],
                                        backgroundColor: 'rgba(0, 123, 255, 0.5)',
                                        borderColor: 'rgba(0, 123, 255, 1)',
                                        borderWidth: 1
                                    }]
                                };
                                
                                const salesChart = new Chart(ctx, {
                                    type: 'bar',
                                    data: salesData,
                                    options: {
                                        responsive: true,
                                        scales: {
                                            y: {
                                                beginAtZero: true
                                            }
                                        }
                                    }
                                });
                            });
                        </script>
                    <?php else : ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات متاحة لهذا العميل.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- بطاقة مبيعات العميل -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-shopping-cart me-1"></i>
            مبيعات العميل
        </div>
        <div class="card-body">
            <?php if (count($client_sales) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>تاريخ المبيعة</th>
                                <th>المبلغ</th>
                                <th>المندوب</th>
                                <th>حالة الدفع</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_sales as $index => $sale_item) : ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo formatDate($sale_item['sale_date']); ?></td>
                                    <td><?php echo formatMoney($sale_item['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($sale_item['salesperson_name']); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $sale_item['payment_status']; ?>">
                                            <?php echo translateSaleStatus($sale_item['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($sale_item['created_at']); ?></td>
                                    <td>
                                        <a href="../sales/view.php?id=<?php echo $sale_item['id']; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i> لا توجد مبيعات متاحة لهذا العميل.
                </div>
                <div class="text-center">
                    <a href="../sales/add.php?client_id=<?php echo $client_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i> إضافة مبيعة جديدة
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- مودال تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رغبتك في حذف هذا العميل؟</p>
                <?php if (count($client_sales) > 0) : ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i> تنبيه: هذا العميل لديه <?php echo count($client_sales); ?> مبيعات مرتبطة به. لا يمكن حذف عميل لديه مبيعات.
                    </div>
                <?php else : ?>
                    <p class="text-danger"><strong>تنبيه:</strong> هذا الإجراء لا يمكن التراجع عنه!</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <?php if (count($client_sales) > 0) : ?>
                    <button type="button" class="btn btn-danger" disabled>
                        لا يمكن الحذف
                    </button>
                <?php else : ?>
                    <a href="index.php?action=delete&id=<?php echo $client_id; ?>" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> نعم، حذف
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>