/**
 * ملف JavaScript الرئيسي للنظام
 */

// عند اكتمال تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // تبديل القائمة الجانبية
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
        });
    }
    
    // تهيئة توست Bootstrap
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    var toastList = toastElList.map(function (toastEl) {
        return new bootstrap.Toast(toastEl);
    });
    toastList.forEach(toast => toast.show());
    
    // تطبيق تنسيق التاريخ العربي
    if (typeof $.fn.datepicker !== 'undefined') {
        $.fn.datepicker.defaults.language = 'ar';
        $.fn.datepicker.defaults.rtl = true;
        $.fn.datepicker.defaults.format = 'yyyy-mm-dd';
        $.fn.datepicker.defaults.autoclose = true;
        
        $('.datepicker').datepicker();
    }
    
    // تهيئة Select2
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            language: 'ar',
            dir: 'rtl'
        });
    }
    
    // تهيئة DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.1/i18n/ar.json'
            },
            responsive: true
        });
    }
    
    // تأكيد الحذف
    $('body').on('click', '.btn-delete', function(e) {
        if (!confirm('هل أنت متأكد من عملية الحذف؟')) {
            e.preventDefault();
        }
    });
    
    // تفعيل الـ tooltips
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // تحديث حالة المبيعة
    $('.change-sale-status').on('change', function() {
        var saleId = $(this).data('id');
        var status = $(this).val();
        
        $.ajax({
            url: '../api/sales.php',
            method: 'POST',
            data: {
                action: 'change_status',
                id: saleId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    // إظهار رسالة نجاح
                    showAlert('success', 'تم تحديث حالة المبيعة بنجاح');
                    
                    // تحديث لون خلية الحالة
                    var statusCell = $('#status-' + saleId);
                    statusCell.removeClass('status-paid status-pending status-cancelled');
                    statusCell.addClass('status-' + status);
                    statusCell.text(translateStatus(status));
                } else {
                    showAlert('danger', response.message || 'حدث خطأ أثناء تحديث حالة المبيعة');
                }
            },
            error: function() {
                showAlert('danger', 'حدث خطأ في الاتصال بالخادم');
            }
        });
    });
    
    // تحديث حالة الزيارة
    $('.change-visit-status').on('change', function() {
        var visitId = $(this).data('id');
        var status = $(this).val();
        
        $.ajax({
            url: '../api/visits.php',
            method: 'POST',
            data: {
                action: 'change_status',
                id: visitId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    // إظهار رسالة نجاح
                    showAlert('success', 'تم تحديث حالة الزيارة بنجاح');
                    
                    // تحديث لون خلية الحالة
                    var statusCell = $('#visit-status-' + visitId);
                    statusCell.removeClass('visit-planned visit-completed visit-cancelled');
                    statusCell.addClass('visit-' + status);
                    statusCell.text(translateVisitStatus(status));
                    
                    // إظهار حقل النتيجة إذا كانت الحالة مكتملة
                    if (status === 'completed') {
                        $('#outcome-container-' + visitId).removeClass('d-none');
                    } else {
                        $('#outcome-container-' + visitId).addClass('d-none');
                    }
                } else {
                    showAlert('danger', response.message || 'حدث خطأ أثناء تحديث حالة الزيارة');
                }
            },
            error: function() {
                showAlert('danger', 'حدث خطأ في الاتصال بالخادم');
            }
        });
    });
    
    // البحث في العملاء
    $('.client-search').on('input', function() {
        var searchTerm = $(this).val();
        
        if (searchTerm.length < 2) {
            $('.client-search-results').empty().hide();
            return;
        }
        
        $.ajax({
            url: '../api/clients.php',
            method: 'GET',
            data: {
                action: 'search',
                term: searchTerm
            },
            success: function(response) {
                var resultsHtml = '';
                
                if (response.length > 0) {
                    for (var i = 0; i < response.length; i++) {
                        resultsHtml += '<div class="client-result" data-id="' + response[i].id + '">' +
                            response[i].name + ' - ' + response[i].phone +
                            '</div>';
                    }
                } else {
                    resultsHtml = '<div class="no-results">لا توجد نتائج</div>';
                }
                
                $('.client-search-results').html(resultsHtml).show();
            }
        });
    });
    
    // اختيار عميل من نتائج البحث
    $(document).on('click', '.client-result', function() {
        var clientId = $(this).data('id');
        var clientName = $(this).text();
        
        $('#client_id').val(clientId);
        $('.client-search').val(clientName);
        $('.client-search-results').hide();
    });
});

/**
 * عرض تنبيه في الصفحة
 * 
 * @param {string} type نوع التنبيه (success, info, warning, danger)
 * @param {string} message نص الرسالة
 */
function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
        message +
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button>' +
        '</div>';
    
    // إضافة التنبيه إلى حاوية التنبيهات إذا وجدت
    if ($('#alerts-container').length) {
        $('#alerts-container').append(alertHtml);
    } else {
        // وإلا قم بإضافته في بداية المحتوى الرئيسي
        $('.container-fluid').prepend(alertHtml);
    }
    
    // إخفاء التنبيه تلقائيًا بعد 5 ثوان
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

/**
 * ترجمة حالة المبيعة
 * 
 * @param {string} status الحالة بالإنجليزية
 * @return {string} الحالة بالعربية
 */
function translateStatus(status) {
    var translations = {
        'paid': 'مدفوعة',
        'pending': 'قيد الانتظار',
        'cancelled': 'ملغية'
    };
    
    return translations[status] || status;
}

/**
 * ترجمة حالة الزيارة
 * 
 * @param {string} status الحالة بالإنجليزية
 * @return {string} الحالة بالعربية
 */
function translateVisitStatus(status) {
    var translations = {
        'planned': 'مخططة',
        'completed': 'مكتملة',
        'cancelled': 'ملغية'
    };
    
    return translations[status] || status;
}

/**
 * تحميل بيانات التقويم
 * 
 * @param {number} month الشهر (1-12)
 * @param {number} year السنة
 */
function loadCalendar(month, year) {
    $.ajax({
        url: '../api/visits.php',
        method: 'GET',
        data: {
            action: 'calendar',
            month: month,
            year: year
        },
        success: function(response) {
            renderCalendar(month, year, response);
        },
        error: function() {
            showAlert('danger', 'حدث خطأ في تحميل بيانات التقويم');
        }
    });
}

/**
 * عرض التقويم
 * 
 * @param {number} month الشهر (1-12)
 * @param {number} year السنة
 * @param {Object} events أحداث التقويم
 */
function renderCalendar(month, year, events) {
    var calendarDiv = document.getElementById("calendar-container");
    calendarDiv.innerHTML = "";
    
    var firstDay = new Date(year, month - 1, 1);
    var lastDay = new Date(year, month, 0);
    var daysInMonth = lastDay.getDate();
    var startingDay = firstDay.getDay(); // 0 = الأحد، 1 = الاثنين، ...
    
    // تعديل لجعل الأسبوع يبدأ بالسبت (6)
    if (startingDay === 0) {
        startingDay = 6;
    } else {
        startingDay = startingDay - 1;
    }
    
    var monthNames = [
        "يناير", "فبراير", "مارس", "إبريل", "مايو", "يونيو",
        "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"
    ];
    
    var dayNames = ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت"];
    
    // إنشاء عنوان التقويم والتنقل
    var calendarHeader = document.createElement("div");
    calendarHeader.className = "d-flex justify-content-between align-items-center mb-3";
    calendarHeader.innerHTML = `
        <button class="btn btn-sm btn-outline-primary" onclick="loadCalendar(${month == 1 ? 12 : month - 1}, ${month == 1 ? year - 1 : year})">
            <i class="fas fa-chevron-right"></i> الشهر السابق
        </button>
        <h4 class="m-0">${monthNames[month - 1]} ${year}</h4>
        <button class="btn btn-sm btn-outline-primary" onclick="loadCalendar(${month == 12 ? 1 : month + 1}, ${month == 12 ? year + 1 : year})">
            الشهر التالي <i class="fas fa-chevron-left"></i>
        </button>
    `;
    calendarDiv.appendChild(calendarHeader);
    
    // إنشاء التقويم
    var table = document.createElement("table");
    table.className = "calendar";
    
    // إنشاء صف أيام الأسبوع
    var headerRow = document.createElement("tr");
    for (var i = 0; i < 7; i++) {
        var th = document.createElement("th");
        // تعديل لجعل الأسبوع يبدأ بالسبت
        var dayIndex = (i + 6) % 7; // 0=السبت، 1=الأحد، ... ، 6=الجمعة
        th.innerHTML = dayNames[dayIndex];
        headerRow.appendChild(th);
    }
    table.appendChild(headerRow);
    
    // إنشاء خلايا التقويم
    var date = 1;
    for (var i = 0; i < 6; i++) { // 6 صفوف كحد أقصى في التقويم
        if (date > daysInMonth) break;
        
        var row = document.createElement("tr");
        
        for (var j = 0; j < 7; j++) {
            if (i === 0 && j < startingDay) {
                // خلايا فارغة قبل اليوم الأول من الشهر
                var cell = document.createElement("td");
                cell.className = "other-month";
                
                // حساب اليوم من الشهر السابق
                var prevMonth = month - 1;
                var prevYear = year;
                if (prevMonth === 0) {
                    prevMonth = 12;
                    prevYear--;
                }
                var prevMonthDays = new Date(prevYear, prevMonth, 0).getDate();
                var prevMonthDay = prevMonthDays - startingDay + j + 1;
                
                cell.innerHTML = `<small class="text-muted">${prevMonthDay}</small>`;
                row.appendChild(cell);
            } 
            else if (date > daysInMonth) {
                // خلايا فارغة بعد اليوم الأخير من الشهر
                var cell = document.createElement("td");
                cell.className = "other-month";
                
                // حساب اليوم من الشهر التالي
                var nextMonthDay = date - daysInMonth;
                date++;
                
                cell.innerHTML = `<small class="text-muted">${nextMonthDay}</small>`;
                row.appendChild(cell);
            } 
            else {
                // أيام الشهر الحالي
                var cell = document.createElement("td");
                
                // التحقق ما إذا كان اليوم الحالي
                var todayDate = new Date();
                if (date === todayDate.getDate() && month === todayDate.getMonth() + 1 && year === todayDate.getFullYear()) {
                    cell.className = "today";
                }
                
                // إضافة رقم اليوم
                cell.innerHTML = `<div class="date-number">${date}</div>`;
                
                // إضافة الأحداث لهذا اليوم
                var dateString = `${year}-${String(month).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                if (events && events[dateString]) {
                    var eventDiv = document.createElement("div");
                    eventDiv.className = "events-container";
                    
                    events[dateString].forEach(function(event) {
                        var eventElement = document.createElement("div");
                        eventElement.className = `event visit-${event.visit_status}`;
                        eventElement.innerHTML = `
                            <strong>${event.visit_time}</strong>: ${event.company_name} 
                            <a href="../sales/visits/view.php?id=${event.id}" class="text-dark">
                                <i class="fas fa-external-link-alt ms-1"></i>
                            </a>
                        `;
                        eventDiv.appendChild(eventElement);
                    });
                    
                    cell.appendChild(eventDiv);
                }
                
                row.appendChild(cell);
                date++;
            }
        }
        
        table.appendChild(row);
    }
    
    calendarDiv.appendChild(table);
}