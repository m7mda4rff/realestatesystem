<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول ومندوب مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../classes/Sale.php';
require_once '../classes/Visit.php';
require_once '../classes/Client.php';
require_once '../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائنات من الفئات
$sale = new Sale($conn);
$visit = new Visit($conn);
$client = new Client($conn);

// متغيرات لتخزين قيم النموذج
$client_id = 0;
$client_name = '';
$visit_id = 0;
$amount = '';
$commission_rate = COMMISSION_DEFAULT_RATE;
$commission_amount = 0;
$description = '';
$sale_date = date('Y-m-d');
$payment_status = 'pending';

// متغيرات لتخزين رسائل الخطأ
$client_id_err = $amount_err = $commission_rate_err = '';

// التحقق من وجود معرف زيارة في URL
if (isset($_GET['from_visit']) && !empty($_GET['from_visit'])) {
    $visit_id = (int)$_GET['from_visit'];
    
    // قراءة بيانات الزيارة
    if ($visit->readOne($visit_id)) {
        if ($visit->visit_status === 'completed' && $visit->salesperson_id === $_SESSION['user_id']) {
            // البحث عن العميل في قاعدة البيانات
            $clients = $client->search($visit->client_name, 1);
            
            if (count($clients) > 0) {
                $client_id = $clients[0]['id'];
                $client_name = $clients[0]['name'];
            }
            
            // ملء التفاصيل من الزيارة
            $description = "تم إنشاء هذه المبيعة من زيارة لشركة {$visit->company_name}.\n\n";
            $description .= "المحتوى: {$visit->purpose}\n\n";
            
            if (!empty($visit->outcome)) {
                $description .= "النتيجة: {$visit->outcome}";
            }
        } else {
            $_SESSION['error_message'] = 'لا يمكن تحويل الزيارة إلى مبيعة إلا إذا كانت مكتملة';
            header('Location: visits/index.php');
            exit;
        }
    } else {
        $_SESSION['error_message'] = 'لم يتم العثور على الزيارة المطلوبة';
        header('Location: visits/index.php');
        exit;
    }
}

// الحصول على قائمة العملاء للاختيار
$clients_list = $client->readAll();

// معالجة النموذج عند الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // التحقق من العميل
    if (empty($_POST['client_id'])) {
        $client_id_err = 'الرجاء اختيار عميل';
    } else {
        $client_id = (int)$_POST['client_id'];
    }
    
    // التحقق من المبلغ
    if (empty(trim($_POST['amount']))) {
        $amount_err = 'الرجاء إدخال مبلغ المبيعة';
    } elseif (!is_numeric(trim($_POST['amount'])) || trim($_POST['amount']) <= 0) {
        $amount_err = 'الرجاء إدخال مبلغ صحيح أكبر من صفر';
    } else {
        $amount = trim($_POST['amount']);
    }
    
    // التحقق من نسبة العمولة
    if (empty(trim($_POST['commission_rate']))) {
        $commission_rate_err = 'الرجاء إدخال نسبة العمولة';
    } elseif (!is_numeric(trim($_POST['commission_rate'])) || trim($_POST['commission_rate']) <= 0) {
        $commission_rate_err = 'الرجاء إدخال نسبة عمولة صحيحة أكبر من صفر';
    } else {
        $commission_rate = trim($_POST['commission_rate']);
    }
    
    // حساب قيمة العمولة
    if (!empty($amount) && !empty($commission_rate) && empty($amount_err) && empty($commission_rate_err)) {
        $commission_amount = ($amount * $commission_rate) / 100;
    }
    
    // تعيين باقي الحقول
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $sale_date = isset($_POST['sale_date']) ? trim($_POST['sale_date']) : date('Y-m-d');
    $payment_status = isset($_POST['payment_status']) ? trim($_POST['payment_status']) : 'pending';
    $visit_id = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;
    
    // التحقق من عدم وجود أخطاء قبل إنشاء المبيعة
    if (empty($client_id_err) && empty($amount_err) && empty($commission_rate_err)) {
        
        // تعيين خصائص المبيعة
        $sale->client_id = $client_id;
        $sale->salesperson_id = $_SESSION['user_id'];
        $sale->amount = $amount;
        $sale->commission_rate = $commission_rate;
        $sale->commission_amount = $commission_amount;
        $sale->description = $description;
        $sale->sale_date = $sale_date;
        $sale->payment_status = $payment_status;
        $sale->created_by = $_SESSION['user_id'];
        
        // محاولة إنشاء المبيعة
        if ($sale->create()) {
            // إذا كانت المبيعة من زيارة، قم بتحديث حالة الزيارة
            if ($visit_id > 0) {
                $visit->readOne($visit_id);
                $visit->outcome = $visit->outcome . "\n\nتم تحويل هذه الزيارة إلى مبيعة بتاريخ " . date('Y-m-d') . " برقم #" . $sale->id;
                $visit->update();
            }
            
            // إعادة التوجيه إلى صفحة المبيعات مع رسالة نجاح
            $_SESSION['success_message'] = 'تم إضافة المبيعة بنجاح';
            header('Location: mysales.php');
            exit;
        } else {
            $error_message = 'حدث خطأ أثناء إضافة المبيعة';
        }
    }
}

// تعيين عنوان الصفحة
$page_title = 'إضافة مبيعة جديدة';

// تضمين ملف رأس الصفحة
include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">إضافة مبيعة جديدة</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">لوحة التحكم</a></li>
        <li class="breadcrumb-item"><a href="mysales.php">مبيعاتي</a></li>
        <li class="breadcrumb-item active">إضافة مبيعة جديدة</li>
    </ol>
    
    <!-- رسائل الخطأ -->
    <?php if (isset($error_message)) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
        </div>
    <?php endif; ?>
    
    <!-- إذا كانت المبيعة من زيارة -->
    <?php if ($visit_id > 0) : ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> أنت تقوم بإنشاء مبيعة من الزيارة رقم #<?php echo $visit_id; ?> الخاصة بشركة <?php echo htmlspecialchars($visit->company_name); ?>.
        </div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-plus me-1"></i>
            تفاصيل المبيعة الجديدة
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . ($visit_id > 0 ? '?from_visit=' . $visit_id : ''); ?>">
                <!-- العميل والتاريخ -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="client_id" class="form-label">العميل <span class="text-danger">*</span></label>
                        <select class="form-select <?php echo (!empty($client_id_err)) ? 'is-invalid' : ''; ?>" id="client_id" name="client_id">
                            <option value="">-- اختر العميل --</option>
                            <?php foreach ($clients_list as $client_item) : ?>
                                <option value="<?php echo $client_item['id']; ?>" <?php echo ($client_id == $client_item['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client_item['name']); ?> - <?php echo htmlspecialchars($client_item['phone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?php echo $client_id_err; ?></div>
                        <div class="form-text">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                <i class="fas fa-plus-circle me-1"></i> إضافة عميل جديد
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="sale_date" class="form-label">تاريخ البيع <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="sale_date" name="sale_date" value="<?php echo $sale_date; ?>" required>
                    </div>
                </div>
                
                <!-- المبلغ ونسبة العمولة -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="amount" class="form-label">قيمة المبيعة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" id="amount" name="amount" placeholder="أدخل قيمة المبيعة" value="<?php echo $amount; ?>" required>
                            <span class="input-group-text">ج.م</span>
                            <div class="invalid-feedback"><?php echo $amount_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="commission_rate" class="form-label">نسبة العمولة <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control <?php echo (!empty($commission_rate_err)) ? 'is-invalid' : ''; ?>" id="commission_rate" name="commission_rate" placeholder="أدخل نسبة العمولة" value="<?php echo $commission_rate; ?>" required>
                            <span class="input-group-text">%</span>
                            <div class="invalid-feedback"><?php echo $commission_rate_err; ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="commission_amount" class="form-label">قيمة العمولة</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="commission_amount" value="<?php echo number_format($commission_amount, 2); ?>" readonly>
                            <span class="input-group-text">ج.م</span>
                        </div>
                        <div class="form-text text-muted">تُحسب تلقائياً بناءً على قيمة المبيعة ونسبة العمولة</div>
                    </div>
                </div>
                
                <!-- حالة الدفع -->
                <div class="mb-3">
                    <label for="payment_status" class="form-label">حالة الدفع</label>
                    <select class="form-select" id="payment_status" name="payment_status">
                        <option value="pending" <?php echo ($payment_status === 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="paid" <?php echo ($payment_status === 'paid') ? 'selected' : ''; ?>>مدفوعة</option>
                        <option value="cancelled" <?php echo ($payment_status === 'cancelled') ? 'selected' : ''; ?>>ملغية</option>
                    </select>
                </div>
                
                <!-- وصف المبيعة -->
                <div class="mb-3">
                    <label for="description" class="form-label">وصف المبيعة</label>
                    <textarea class="form-control" id="description" name="description" rows="5" placeholder="أدخل وصف المبيعة وتفاصيلها"><?php echo $description; ?></textarea>
                </div>
                
                <!-- إذا كانت المبيعة من زيارة، قم بتمرير معرف الزيارة -->
                <?php if ($visit_id > 0) : ?>
                    <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
                <?php endif; ?>
                
                <!-- زر الحفظ والإلغاء -->
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ المبيعة
                    </button>
                    <a href="<?php echo $visit_id > 0 ? 'visits/view.php?id=' . $visit_id : 'mysales.php'; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-1"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نموذج إضافة عميل جديد -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addClientModalLabel">إضافة عميل جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <form id="addClientForm">
                    <div class="mb-3">
                        <label for="client_name" class="form-label">اسم العميل <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="client_name" name="client_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="client_phone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="client_phone" name="client_phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="client_email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="client_email" name="client_email">
                    </div>
                    <div class="mb-3">
                        <label for="client_address" class="form-label">العنوان</label>
                        <textarea class="form-control" id="client_address" name="client_address" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="saveClientBtn">حفظ العميل</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // حساب قيمة العمولة عند تغيير المبلغ أو النسبة
    const amountInput = document.getElementById('amount');
    const commissionRateInput = document.getElementById('commission_rate');
    const commissionAmountInput = document.getElementById('commission_amount');
    
    function calculateCommission() {
        const amount = parseFloat(amountInput.value) || 0;
        const rate = parseFloat(commissionRateInput.value) || 0;
        const commission = (amount * rate) / 100;
        
        commissionAmountInput.value = commission.toFixed(2);
    }
    
    amountInput.addEventListener('input', calculateCommission);
    commissionRateInput.addEventListener('input', calculateCommission);
    
    // إضافة عميل جديد
    const saveClientBtn = document.getElementById('saveClientBtn');
    const clientSelect = document.getElementById('client_id');
    
    saveClientBtn.addEventListener('click', function() {
        const clientName = document.getElementById('client_name').value;
        const clientPhone = document.getElementById('client_phone').value;
        const clientEmail = document.getElementById('client_email').value;
        const clientAddress = document.getElementById('client_address').value;
        
        if (!clientName || !clientPhone) {
            alert('الرجاء إدخال اسم العميل ورقم الهاتف');
            return;
        }
        
        // إرسال بيانات العميل إلى الخادم
        fetch('../api/clients.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'add',
                name: clientName,
                phone: clientPhone,
                email: clientEmail,
                address: clientAddress,
                created_by: <?php echo $_SESSION['user_id']; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // إضافة العميل الجديد إلى قائمة العملاء
                const newOption = new Option(clientName + ' - ' + clientPhone, data.client_id, true, true);
                clientSelect.add(newOption);
                
                // إغلاق النموذج
                const modal = bootstrap.Modal.getInstance(document.getElementById('addClientModal'));
                modal.hide();
                
                // إعادة تعيين النموذج
                document.getElementById('addClientForm').reset();
            } else {
                alert('حدث خطأ: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ أثناء إضافة العميل');
        });
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../includes/footer.php';
?>