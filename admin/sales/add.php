<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وله صلاحيات المسؤول
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات المتاحة
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Sale.php';
require_once '../../classes/User.php';
require_once '../../classes/Client.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$user = new User($conn);
$client = new Client($conn);

// تعريف متغيرات الخطأ والنجاح
$errors = [];
$success = false;

// الحصول على قائمة العملاء ومندوبي المبيعات
$clients = $client->readAll();
// استدعاء دالة readAll مع معلمة الدور المناسبة
$salespeople = $user->readAll('salesperson');

// تعيين المندوب الافتراضي إذا كان المستخدم مندوب مبيعات
$default_salesperson_id = ($_SESSION['role'] === 'salesperson') ? $_SESSION['user_id'] : '';

// التحقق إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // استلام البيانات من النموذج
    $sale_data = [
        'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0,
        'salesperson_id' => isset($_POST['salesperson_id']) ? (int)$_POST['salesperson_id'] : 0,
        'amount' => isset($_POST['amount']) ? (float)str_replace(',', '', $_POST['amount']) : 0,
        'sale_date' => isset($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d'),
        'payment_status' => isset($_POST['payment_status']) ? $_POST['payment_status'] : 'pending',
        'payment_method' => isset($_POST['payment_method']) ? $_POST['payment_method'] : '',
        'payment_details' => isset($_POST['payment_details']) ? $_POST['payment_details'] : '',
        'description' => isset($_POST['description']) ? $_POST['description'] : '',
        'notes' => isset($_POST['notes']) ? $_POST['notes'] : '',
        'created_by' => $_SESSION['user_id']
    ];

    // حساب العمولة تلقائيًا
    $commission_rate = COMMISSION_DEFAULT_RATE; // يفترض أن هذا الثابت معرف في ملف constants.php
    $sale_data['commission_rate'] = $commission_rate;
    $sale_data['commission_amount'] = ($sale_data['amount'] * $commission_rate) / 100;

    // التحقق من صحة البيانات
    if ($sale_data['client_id'] <= 0) {
        $errors[] = 'يرجى اختيار عميل صحيح';
    }

    if ($sale_data['salesperson_id'] <= 0) {
        $errors[] = 'يرجى اختيار مندوب مبيعات صحيح';
    }

    if (empty($sale_data['sale_date'])) {
        $errors[] = 'يرجى تحديد تاريخ البيع';
    }

    if ($sale_data['amount'] <= 0) {
        $errors[] = 'يرجى إدخال مبلغ البيع بشكل صحيح';
    }

    if (empty($sale_data['payment_method'])) {
        $errors[] = 'يرجى اختيار طريقة الدفع';
    }

    // إذا لم توجد أخطاء، قم بإنشاء عملية البيع
    if (empty($errors)) {
        try {
            // تعيين خصائص المبيعة
            $sale->client_id = $sale_data['client_id'];
            $sale->salesperson_id = $sale_data['salesperson_id'];
            $sale->amount = $sale_data['amount'];
            $sale->commission_rate = $sale_data['commission_rate'];
            $sale->commission_amount = $sale_data['commission_amount'];
            $sale->description = $sale_data['description'];
            $sale->sale_date = $sale_data['sale_date'];
            $sale->payment_status = $sale_data['payment_status'];
            $sale->created_by = $sale_data['created_by'];
            
            // إنشاء سجل المبيعات
            if ($sale->create()) {
                // تعيين رسالة النجاح
                $_SESSION['success_message'] = 'تمت إضافة المبيعة بنجاح';
                
                // إعادة توجيه إلى صفحة المبيعات
                header('Location: index.php');
                exit;
            } else {
                $errors[] = 'حدث خطأ أثناء إضافة المبيعة';
            }
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة مبيعة جديدة';

// تحديد الصفحة النشطة للقائمة الجانبية
$active_page = 'sales';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة مبيعة جديدة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="index.php">المبيعات</a></li>
        <li class="breadcrumb-item active">إضافة مبيعة</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus me-1"></i>
            إضافة مبيعة جديدة
        </div>
        <div class="card-body">
            <?php if (!empty($errors)) : ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="add-sale-form">
                <div class="row mb-3">
                    <!-- تفاصيل العميل والمندوب -->
                    <div class="col-md-6">
                        <h4 class="mb-3">تفاصيل الأطراف</h4>
                        
                        <div class="mb-3">
                            <label for="client_id" class="form-label">العميل <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" name="client_id" id="client_id" required>
                                    <option value="">-- اختر العميل --</option>
                                    <?php foreach ($clients as $client_item) : ?>
                                        <option value="<?php echo $client_item['id']; ?>" <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client_item['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client_item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../clients/add.php" class="btn btn-outline-secondary" target="_blank" title="إضافة عميل جديد">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="salesperson_id" class="form-label">مندوب المبيعات <span class="text-danger">*</span></label>
                            <select class="form-select" name="salesperson_id" id="salesperson_id" required>
                                <option value="">-- اختر المندوب --</option>
                                <?php foreach ($salespeople as $salesperson) : ?>
                                    <option value="<?php echo $salesperson['id']; ?>" <?php echo (isset($_POST['salesperson_id']) ? $_POST['salesperson_id'] : $default_salesperson_id) == $salesperson['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($salesperson['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">وصف المبيعة</label>
                            <textarea class="form-control" name="description" id="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- تفاصيل المبيعة -->
                    <div class="col-md-6">
                        <h4 class="mb-3">تفاصيل المبيعة</h4>
                        
                        <div class="mb-3">
                            <label for="sale_date" class="form-label">تاريخ البيع <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="sale_date" id="sale_date" value="<?php echo isset($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">مبلغ البيع <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="amount" id="amount" value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" placeholder="0.00" required>
                                <span class="input-group-text">ج.م</span>
                            </div>
                            <div class="form-text">سيتم حساب العمولة تلقائيًا بنسبة <?php echo COMMISSION_DEFAULT_RATE ?? 2.5; ?>% من قيمة المبيعة</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_status" class="form-label">حالة الدفع <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_status" id="payment_status" required>
                                <option value="paid" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'paid') ? 'selected' : ''; ?>>مدفوع</option>
                                <option value="pending" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'pending') ? 'selected' : ((!isset($_POST['payment_status'])) ? 'selected' : ''); ?>>قيد الانتظار</option>
                                <option value="cancelled" <?php echo (isset($_POST['payment_status']) && $_POST['payment_status'] == 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <!-- تفاصيل الدفع -->
                    <div class="col-md-12">
                        <h4 class="mb-3">تفاصيل الدفع</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                                    <select class="form-select" name="payment_method" id="payment_method" required>
                                        <option value="">-- اختر طريقة الدفع --</option>
                                        <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>>نقداً</option>
                                        <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>>تحويل بنكي</option>
                                        <option value="cheque" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cheque') ? 'selected' : ''; ?>>شيك</option>
                                        <option value="installment" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'installment') ? 'selected' : ''; ?>>تقسيط</option>
                                        <option value="other" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'other') ? 'selected' : ''; ?>>أخرى</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_details" class="form-label">تفاصيل الدفع</label>
                                    <input type="text" class="form-control" name="payment_details" id="payment_details" value="<?php echo isset($_POST['payment_details']) ? htmlspecialchars($_POST['payment_details']) : ''; ?>" placeholder="مثال: رقم الشيك، تفاصيل التقسيط...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">ملاحظات</label>
                    <textarea class="form-control" name="notes" id="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ المبيعة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تنسيق حقل المبلغ بالفواصل
    document.getElementById('amount').addEventListener('blur', function() {
        if (this.value) {
            const num = parseFloat(this.value.replace(/,/g, ''));
            if (!isNaN(num)) {
                this.value = num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }
    });
    
    // التحقق من النموذج قبل الإرسال
    document.getElementById('add-sale-form').addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = ['client_id', 'salesperson_id', 'sale_date', 'amount', 'payment_status', 'payment_method'];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field);
            if (!element.value.trim()) {
                element.classList.add('is-invalid');
                isValid = false;
            } else {
                element.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('يرجى ملء جميع الحقول المطلوبة');
        }
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>