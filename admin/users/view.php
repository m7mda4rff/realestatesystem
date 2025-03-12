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
require_once '../../classes/User.php';
require_once '../../classes/Sale.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة المستخدم
$user = new User($conn);

// التحقق من وجود معرف المستخدم في URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'معرف المستخدم غير صحيح';
    header('Location: index.php');
    exit;
}

// الحصول على معرف المستخدم
$user_id = (int)$_GET['id'];

// محاولة قراءة تفاصيل المستخدم
if (!$user->readOne($user_id)) {
    $_SESSION['error_message'] = 'لم يتم العثور على المستخدم المطلوب';
    header('Location: index.php');
    exit;
}

// الحصول على اسم المدير (إذا كان المستخدم مندوب مبيعات)
$manager_name = '';
if ($user->role === 'salesperson' && $user->manager_id) {
    $manager = new User($conn);
    $manager->readOne($user->manager_id);
    $manager_name = $manager->full_name;
}

// الحصول على إحصائيات المبيعات إذا كان المستخدم مندوبًا أو مديرًا
$sales_stats = [];
if ($user->role === 'salesperson' || $user->role === 'manager') {
    $sale = new Sale($conn);
    $sales_stats = $sale->getSaleStats($user_id);
}

// تعيين عنوان الصفحة
$page_title = 'عرض بيانات المستخدم';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">عرض بيانات المستخدم</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">إدارة المستخدمين</a></li>
        <li class="breadcrumb-item active">عرض بيانات المستخدم</li>
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
        <div class="col-xl-4">
            <!-- بطاقة معلومات المستخدم -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    معلومات المستخدم
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img class="img-account-profile rounded-circle mb-2" src="<?php echo ASSETS_URL; ?>/images/user.png" alt="صورة المستخدم" width="150">
                        <div class="small font-italic text-muted mb-2">صورة المستخدم</div>
                        <h4><?php echo htmlspecialchars($user->full_name); ?></h4>
                        <div class="mb-2">
                            <span class="badge bg-<?php 
                                echo $user->role === 'admin' ? 'danger' : ($user->role === 'manager' ? 'warning' : 'info'); 
                            ?>">
                                <?php 
                                    echo $user->role === 'admin' ? 'مسؤول النظام' : ($user->role === 'manager' ? 'مدير مبيعات' : 'مندوب مبيعات'); 
                                ?>
                            </span>
                        </div>
                        <p class="mb-0">
                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user->email); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-phone me-1"></i> <?php echo $user->phone ? htmlspecialchars($user->phone) : 'غير متوفر'; ?>
                        </p>
                        <?php if ($user->role === 'salesperson' && !empty($manager_name)) : ?>
                            <p class="mb-0">
                                <i class="fas fa-user-tie me-1"></i> المدير: <?php echo htmlspecialchars($manager_name); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> تعديل البيانات
                        </a>
                        <?php if ($user_id !== $_SESSION['user_id'] && $user_id !== 1) : ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash me-1"></i> حذف المستخدم
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <small class="text-muted">
                        تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($user->created_at)); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <!-- بطاقة التفاصيل الإضافية -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-info-circle me-1"></i>
                    تفاصيل إضافية
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="fw-bold">اسم المستخدم:</label>
                        <p><?php echo htmlspecialchars($user->username); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">البريد الإلكتروني:</label>
                        <p><?php echo htmlspecialchars($user->email); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">الدور:</label>
                        <p>
                            <?php 
                                echo $user->role === 'admin' ? 'مسؤول النظام' : ($user->role === 'manager' ? 'مدير مبيعات' : 'مندوب مبيعات'); 
                            ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">رقم الهاتف:</label>
                        <p><?php echo $user->phone ? htmlspecialchars($user->phone) : 'غير متوفر'; ?></p>
                    </div>
                    
                    <?php if ($user->role === 'salesperson' && !empty($manager_name)) : ?>
                        <div class="mb-3">
                            <label class="fw-bold">المدير المسؤول:</label>
                            <p><?php echo htmlspecialchars($manager_name); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="fw-bold">تاريخ التسجيل:</label>
                        <p><?php echo date('Y-m-d H:i:s', strtotime($user->created_at)); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">آخر تحديث:</label>
                        <p><?php echo $user->updated_at ? date('Y-m-d H:i:s', strtotime($user->updated_at)) : 'لا يوجد تحديث'; ?></p>
                    </div>
                </div>
            </div>
            
            <!-- إذا كان المستخدم مندوبًا أو مديرًا، عرض إحصائيات المبيعات -->
            <?php if (($user->role === 'salesperson' || $user->role === 'manager') && !empty($sales_stats)) : ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        إحصائيات المبيعات
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-primary text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">إجمالي المبيعات</div>
                                                <div class="display-6"><?php echo formatMoney($sales_stats['total_amount'] ?? 0); ?></div>
                                            </div>
                                            <div><i class="fas fa-chart-line fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-success text-white h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="small">عدد المبيعات</div>
                                                <div class="display-6"><?php echo $sales_stats['total_count'] ?? 0; ?></div>
                                            </div>
                                            <div><i class="fas fa-shopping-cart fa-3x"></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- رابط لعرض المزيد من التفاصيل -->
                        <div class="text-center mt-3">
                            <a href="../sales/index.php?salesperson=<?php echo $user_id; ?>" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> عرض تفاصيل المبيعات
                            </a>
                        </div>
                    </div>
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
                <p>هل أنت متأكد من رغبتك في حذف المستخدم <strong><?php echo htmlspecialchars($user->full_name); ?></strong>؟</p>
                <p class="text-danger"><strong>تنبيه:</strong> هذا الإجراء لا يمكن التراجع عنه وسيتم حذف جميع بيانات المستخدم.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <a href="delete.php?id=<?php echo $user_id; ?>" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> نعم، حذف المستخدم
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>