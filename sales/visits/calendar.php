<?php
// بدء جلسة
session_start();

// التحقق إذا كان المستخدم مسجل الدخول وهو مندوب مبيعات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'salesperson') {
    header('Location: ../../auth/login.php');
    exit;
}

// تضمين ملفات الإعدادات والفئات
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../classes/Visit.php';
require_once '../../includes/functions.php';

// إنشاء اتصال بقاعدة البيانات
$db = new Database();
$conn = $db->getConnection();

// إنشاء كائن من فئة الزيارات
$visit = new Visit($conn);

// تحديد الشهر والسنة المطلوبين
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// التحقق من صحة الشهر والسنة
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// الحصول على بيانات الزيارات للتقويم
$calendar_data = $visit->getCalendarData($_SESSION['user_id'], $month, $year);

// الحصول على معلومات الشهر
$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);
$month_name_ar = translateMonthName($month_name);
$start_day = date('N', $first_day); // 1 (للاثنين) إلى 7 (للأحد)

// تعديل لجعل الأسبوع يبدأ بالسبت (6، 7، 1، 2، 3، 4، 5)
$start_day = ($start_day == 7) ? 1 : $start_day + 1;

// الحصول على الشهر القادم والسابق للتنقل
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month <= 0) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// تعيين عنوان الصفحة
$page_title = 'تقويم الزيارات';

// تضمين ملف رأس الصفحة
include_once '../../includes/header.php';

// دالة لترجمة أسماء الأشهر للعربية
function translateMonthName($month_name) {
    $months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
    
    return $months[$month_name] ?? $month_name;
}

// دالة لترجمة أسماء الأيام للعربية
function translateDayName($day_name) {
    $days = [
        'Monday' => 'الاثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
        'Sunday' => 'الأحد'
    ];
    
    return $days[$day_name] ?? $day_name;
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">تقويم الزيارات</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../../index.php">الرئيسية</a></li>
        <li class="breadcrumb-item"><a href="index.php">الزيارات الخارجية</a></li>
        <li class="breadcrumb-item active">تقويم الزيارات</li>
    </ol>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-calendar me-1"></i>
            تقويم الزيارات - <?php echo $month_name_ar . ' ' . $year; ?>
        </div>
        <div class="card-body">
            <!-- أزرار التنقل بين الأشهر -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-chevron-right"></i> الشهر السابق
                    </a>
                </div>
                <div class="col-md-4 text-center">
                    <h3><?php echo $month_name_ar . ' ' . $year; ?></h3>
                </div>
                <div class="col-md-4 text-end">
                    <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary">
                        الشهر التالي <i class="fas fa-chevron-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- التقويم -->
            <div class="table-responsive">
                <table class="calendar table table-bordered">
                    <thead class="bg-light">
                        <tr>
                            <th>السبت</th>
                            <th>الأحد</th>
                            <th>الاثنين</th>
                            <th>الثلاثاء</th>
                            <th>الأربعاء</th>
                            <th>الخميس</th>
                            <th>الجمعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // تحديد اليوم الحالي للتمييز في التقويم
                        $current_day = date('j');
                        $current_month = date('n');
                        $current_year = date('Y');
                        
                        // بناء التقويم
                        $day_count = 1;
                        $cell_count = 1;
                        
                        // تحديد عدد الأسابيع في الشهر
                        $num_of_weeks = ceil(($days_in_month + $start_day - 1) / 7);
                        
                        // بناء الصفوف والخلايا
                        for ($week = 1; $week <= $num_of_weeks; $week++) {
                            echo '<tr class="calendar-row" style="height: 120px;">';
                            
                            // بناء الخلايا في كل صف
                            for ($day_of_week = 1; $day_of_week <= 7; $day_of_week++) {
                                // تحديد ما إذا كانت الخلية الحالية هي من الشهر الحالي
                                if (($day_count > 1 && $day_count <= $days_in_month) || ($day_count == 1 && $day_of_week >= $start_day)) {
                                    // تحديد ما إذا كان اليوم هو اليوم الحالي
                                    $is_today = ($day_count == $current_day && $month == $current_month && $year == $current_year);
                                    
                                    echo '<td class="calendar-day' . ($is_today ? ' today bg-light' : '') . '">';
                                    
                                    // رقم اليوم
                                    echo '<div class="day-number">' . $day_count . '</div>';
                                    
                                    // الزيارات لهذا اليوم
                                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day_count);
                                    
                                    if (isset($calendar_data[$current_date])) {
                                        echo '<div class="day-events">';
                                        foreach ($calendar_data[$current_date] as $day_visit) {
                                            $status_class = 'visit-' . $day_visit['visit_status'];
                                            echo '<div class="calendar-event ' . $status_class . '">';
                                            echo '<span class="event-time">' . $day_visit['visit_time'] . '</span><br>';
                                            echo '<span class="event-title">' . htmlspecialchars($day_visit['company_name']) . '</span>';
                                            echo '<a href="view.php?id=' . $day_visit['id'] . '" class="event-view-link ms-1" title="عرض التفاصيل"><i class="fas fa-eye"></i></a>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    
                                    echo '</td>';
                                    
                                    $day_count++;
                                } else {
                                    // خلايا خارج الشهر الحالي
                                    echo '<td class="calendar-day other-month">';
                                    
                                    // الأيام قبل بداية الشهر
                                    if ($day_count == 1) {
                                        $prev_month_day = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year)) - ($start_day - $day_of_week) + 1;
                                        echo '<div class="day-number text-muted">' . $prev_month_day . '</div>';
                                    } 
                                    // الأيام بعد نهاية الشهر
                                    else {
                                        $next_month_day = $cell_count - ($days_in_month + $start_day - 1);
                                        echo '<div class="day-number text-muted">' . $next_month_day . '</div>';
                                    }
                                    
                                    echo '</td>';
                                }
                                
                                $cell_count++;
                            }
                            
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- مفتاح التقويم -->
            <div class="mt-4">
                <h5>مفتاح الحالات:</h5>
                <div class="calendar-key">
                    <span class="badge visit-planned p-2 me-2">مخططة</span>
                    <span class="badge visit-completed p-2 me-2">مكتملة</span>
                    <span class="badge visit-cancelled p-2">ملغية</span>
                </div>
            </div>
            
            <!-- زر إضافة زيارة جديدة -->
            <div class="mt-4">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> إضافة زيارة جديدة
                </a>
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-list me-1"></i> عرض قائمة الزيارات
                </a>
            </div>
        </div>
    </div>
</div>

<!-- نافذة منبثقة لعرض تفاصيل الزيارة -->
<div class="modal fade" id="visitModal" tabindex="-1" aria-labelledby="visitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitModalLabel">تفاصيل الزيارة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <div id="visit-details-container">
                    <!-- سيتم ملء البيانات هنا ديناميكيًا -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<style>
/* أنماط إضافية للتقويم */
.calendar {
    table-layout: fixed;
}

.calendar-day {
    height: 120px;
    vertical-align: top;
    width: 14.28%;
    padding: 5px !important;
    position: relative;
}

.day-number {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.calendar-event {
    padding: 2px 5px;
    margin-bottom: 3px;
    border-radius: 4px;
    font-size: 12px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    position: relative;
}

.today {
    background-color: rgba(0, 123, 255, 0.1);
}

.other-month {
    background-color: #f8f9fa;
}

.event-time {
    font-weight: bold;
}

.visit-planned {
    background-color: #cce5ff;
    color: #004085;
    border: 1px solid #b8daff;
}

.visit-completed {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.visit-cancelled {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.event-view-link {
    position: absolute;
    top: 2px;
    right: 4px;
    color: inherit;
}

.calendar-key {
    display: flex;
    align-items: center;
    margin: 10px 0;
}

/* تعديل للأجهزة المحمولة */
@media (max-width: 768px) {
    .calendar-day {
        height: 80px;
        font-size: 11px;
    }
    
    .day-number {
        font-size: 14px;
    }
    
    .calendar-event {
        padding: 1px 3px;
        font-size: 10px;
    }
}
</style>

<script>
// عرض تفاصيل الزيارة عند النقر على الزيارة
$(document).ready(function() {
    $('.calendar-event').click(function(e) {
        // منع الانتقال إلى رابط العرض
        e.preventDefault();
        
        // الحصول على معرف الزيارة من الرابط
        var visitLink = $(this).find('.event-view-link').attr('href');
        var visitId = visitLink.split('id=')[1];
        
        // طلب AJAX للحصول على تفاصيل الزيارة
        $.ajax({
            url: '../../api/visits.php',
            method: 'GET',
            data: {
                action: 'get_visit',
                id: visitId
            },
            dataType: 'json',
            success: function(response) {
                if (response) {
                    // تحديد حالة الزيارة بالعربية
                    var status = '';
                    switch(response.visit_status) {
                        case 'planned':
                            status = '<span class="badge visit-planned">مخططة</span>';
                            break;
                        case 'completed':
                            status = '<span class="badge visit-completed">مكتملة</span>';
                            break;
                        case 'cancelled':
                            status = '<span class="badge visit-cancelled">ملغية</span>';
                            break;
                    }
                    
                    // تنسيق التاريخ والوقت
                    var dateTime = new Date(response.visit_time);
                    var formattedDate = dateTime.toLocaleDateString('ar-EG');
                    var formattedTime = dateTime.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
                    
                    // إنشاء HTML لعرض التفاصيل
                    var detailsHtml = `
                        <h5 class="text-primary">${response.company_name}</h5>
                        <div class="mb-3">${status}</div>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">العميل</th>
                                <td>${response.client_name}</td>
                            </tr>
                            <tr>
                                <th>رقم الهاتف</th>
                                <td>${response.client_phone || 'غير محدد'}</td>
                            </tr>
                            <tr>
                                <th>التاريخ والوقت</th>
                                <td>${formattedDate} - ${formattedTime}</td>
                            </tr>
                            <tr>
                                <th>الغرض</th>
                                <td>${response.purpose || 'غير محدد'}</td>
                            </tr>
                        </table>
                        <div class="mt-3">
                            <a href="view.php?id=${response.id}" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye me-1"></i> عرض التفاصيل الكاملة
                            </a>
                            <a href="edit.php?id=${response.id}" class="btn btn-sm btn-info">
                                <i class="fas fa-edit me-1"></i> تعديل
                            </a>
                        </div>
                    `;
                    
                    // عرض التفاصيل في النافذة المنبثقة
                    $('#visit-details-container').html(detailsHtml);
                    $('#visitModalLabel').text('تفاصيل زيارة ' + response.company_name);
                    $('#visitModal').modal('show');
                } else {
                    alert('لا يمكن الحصول على تفاصيل الزيارة');
                }
            },
            error: function() {
                alert('حدث خطأ أثناء جلب بيانات الزيارة');
            }
        });
    });
});
</script>

<?php
// تضمين ملف تذييل الصفحة
include_once '../../includes/footer.php';
?>