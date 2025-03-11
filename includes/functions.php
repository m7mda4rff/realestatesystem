<?php
/**
 * ملف الدوال المساعدة
 * يحتوي على مجموعة من الدوال المساعدة المستخدمة في النظام
 */

/**
 * دالة لتنسيق الأرقام بصيغة النقود
 * 
 * @param float $amount المبلغ
 * @param string $currency رمز العملة (اختياري)
 * @return string المبلغ المنسق
 */
function formatMoney($amount, $currency = 'ج.م') {
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * دالة لتنسيق التواريخ
 * 
 * @param string $date التاريخ
 * @param string $format صيغة التاريخ (اختياري)
 * @return string التاريخ المنسق
 */
function formatDate($date, $format = 'Y-m-d') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * دالة لترجمة حالة المبيعة
 * 
 * @param string $status الحالة بالإنجليزية
 * @return string الحالة بالعربية
 */
function translateSaleStatus($status) {
    $statuses = [
        'paid' => 'مدفوعة',
        'pending' => 'قيد الانتظار',
        'cancelled' => 'ملغية'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * دالة لترجمة حالة العمولة
 * 
 * @param string $status الحالة بالإنجليزية
 * @return string الحالة بالعربية
 */
function translateCommissionStatus($status) {
    $statuses = [
        'paid' => 'مدفوعة',
        'pending' => 'قيد الانتظار'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * دالة لترجمة حالة الزيارة
 * 
 * @param string $status الحالة بالإنجليزية
 * @return string الحالة بالعربية
 */
function translateVisitStatus($status) {
    $statuses = [
        'planned' => 'مخططة',
        'completed' => 'مكتملة',
        'cancelled' => 'ملغية'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * دالة لحساب نسبة التحقيق
 * 
 * @param float $achieved المبلغ المحقق
 * @param float $target المبلغ المستهدف
 * @return float نسبة التحقيق
 */
function calculateAchievement($achieved, $target) {
    if ($target <= 0) {
        return 0;
    }
    
    $percentage = ($achieved / $target) * 100;
    return round($percentage, 1);
}

/**
 * دالة لإنشاء لون CSS بناءً على نسبة معينة
 * 
 * @param float $percentage النسبة المئوية
 * @return string لون CSS
 */
function getColorByPercentage($percentage) {
    if ($percentage >= 100) {
        return 'success';
    } elseif ($percentage >= 75) {
        return 'info';
    } elseif ($percentage >= 50) {
        return 'primary';
    } elseif ($percentage >= 25) {
        return 'warning';
    } else {
        return 'danger';
    }
}

/**
 * دالة للتحقق من صلاحيات الوصول
 * 
 * @param string|array $allowed_roles الأدوار المسموح لها
 * @return boolean نتيجة التحقق
 */
function checkPermission($allowed_roles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    if (is_array($allowed_roles)) {
        return in_array($_SESSION['role'], $allowed_roles);
    } else {
        return $_SESSION['role'] === $allowed_roles;
    }
}

/**
 * دالة لإنشاء تنبيه
 * 
 * @param string $type نوع التنبيه (نجاح، خطأ، تحذير، معلومات)
 * @param string $message نص الرسالة
 * @return string كود HTML للتنبيه
 */
function createAlert($type, $message) {
    $icon = '';
    
    switch ($type) {
        case 'success':
            $icon = '<i class="fas fa-check-circle me-2"></i>';
            break;
        case 'danger':
            $icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            break;
        case 'warning':
            $icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            break;
        case 'info':
            $icon = '<i class="fas fa-info-circle me-2"></i>';
            break;
    }
    
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $icon . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>
            </div>';
}

/**
 * دالة لإنشاء معلومات الترويسة
 * 
 * @param array $cards بيانات البطاقات
 * @return string كود HTML للبطاقات
 */
function createInfoCards($cards) {
    $html = '<div class="row mb-4">';
    
    foreach ($cards as $card) {
        $icon = isset($card['icon']) ? $card['icon'] : 'fa-info-circle';
        $color = isset($card['color']) ? $card['color'] : 'primary';
        $value = isset($card['value']) ? $card['value'] : '0';
        $title = isset($card['title']) ? $card['title'] : '';
        $link = isset($card['link']) ? $card['link'] : '#';
        
        $html .= '<div class="col-xl-3 col-md-6">
                    <div class="card bg-' . $color . ' text-white mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0">' . $value . '</h4>
                                    <div class="small">' . $title . '</div>
                                </div>
                                <div class="ms-2">
                                    <i class="fas ' . $icon . ' fa-3x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="' . $link . '">عرض التفاصيل</a>
                            <div class="small text-white"><i class="fas fa-angle-left"></i></div>
                        </div>
                    </div>
                </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * دالة لإنشاء شريط تقدم
 * 
 * @param float $value القيمة الحالية
 * @param float $max القيمة القصوى
 * @param string $title العنوان (اختياري)
 * @return string كود HTML لشريط التقدم
 */
function createProgressBar($value, $max, $title = '') {
    $percentage = calculateAchievement($value, $max);
    $color = getColorByPercentage($percentage);
    
    $html = '<div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tasks me-1"></i>
                    ' . $title . '
                </div>
                <div class="card-body">
                    <h5 class="card-title">' . formatMoney($value) . ' من ' . formatMoney($max) . '</h5>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-' . $color . '" role="progressbar" style="width: ' . min($percentage, 100) . '%;" aria-valuenow="' . $percentage . '" aria-valuemin="0" aria-valuemax="100">
                            ' . $percentage . '%
                        </div>
                    </div>
                </div>
            </div>';
    
    return $html;
}
?>