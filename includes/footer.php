</main>
            <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">جميع الحقوق محفوظة &copy; <?php echo SYSTEM_NAME . ' ' . date('Y'); ?></div>
                        <div>
                            <a href="#">سياسة الخصوصية</a>
                            &middot;
                            <a href="#">شروط الاستخدام</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- تضمين jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- تضمين Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- تضمين Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- تضمين DataTables -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- تضمين Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- تضمين Moment.js و DateRangePicker -->
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
    <!-- تضمين ملف JavaScript الرئيسي -->
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    
    <!-- أي ملفات JavaScript إضافية مخصصة للصفحة -->
    <?php if (isset($page_specific_js)) { echo $page_specific_js; } ?>
    
    <script>
    $(document).ready(function() {
        // تهيئة Select2
        $('.select2').select2({
            theme: 'bootstrap-5',
            language: 'ar',
            dir: 'rtl'
        });
        
        // تهيئة DataTables
        $('.datatable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.1/i18n/ar.json'
            }
        });
        
        // تحميل الإشعارات
        function loadNotifications() {
            $.ajax({
                url: '<?php echo URL_ROOT; ?>/api/notifications.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    var notificationsDropdown = $('#navbarDropdownNotifications').next('.dropdown-menu');
                    
                    // حذف الإشعارات القديمة
                    notificationsDropdown.find('.notification-item').remove();
                    
                    // إضافة الإشعارات الجديدة
                    if (data.length > 0) {
                        $.each(data, function(index, notification) {
                            var notificationHtml = '<li class="notification-item"><a class="dropdown-item" href="#" data-id="' + notification.id + '">' +
                                                   '<small class="text-muted">' + notification.created_at + '</small><br>' +
                                                   notification.title +
                                                   '</a></li>';
                            
                            notificationsDropdown.find('.dropdown-divider').after(notificationHtml);
                        });
                    } else {
                        var noNotificationsHtml = '<li class="notification-item"><a class="dropdown-item text-muted" href="#">لا توجد إشعارات جديدة</a></li>';
                        notificationsDropdown.find('.dropdown-divider').after(noNotificationsHtml);
                    }
                    
                    // تعيين عدد الإشعارات
                    var badgeHtml = data.length > 0 ? '<span class="badge bg-danger">' + data.length + '</span>' : '';
                    $('#navbarDropdownNotifications').find('.badge').remove();
                    $('#navbarDropdownNotifications').append(badgeHtml);
                },
                error: function() {
                    console.error('حدث خطأ أثناء تحميل الإشعارات');
                }
            });
        }
        
        // تحديث الإشعارات كل 60 ثانية
        loadNotifications();
        setInterval(loadNotifications, 60000);
        
        // تعليم الإشعار كمقروء عند النقر عليه
        $(document).on('click', '.notification-item a', function(e) {
            var notificationId = $(this).data('id');
            
            $.ajax({
                url: '<?php echo URL_ROOT; ?>/api/notifications.php',
                method: 'POST',
                data: {
                    action: 'mark_as_read',
                    id: notificationId
                },
                success: function() {
                    loadNotifications();
                }
            });
        });
    });
    </script>
</body>
</html>