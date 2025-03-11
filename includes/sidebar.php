<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <!-- العناصر المشتركة بين جميع المستخدمين -->
                <div class="sb-sidenav-menu-heading">الرئيسية</div>
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    لوحة التحكم
                </a>
                
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <!-- عناصر خاصة بالأدمن -->
                <div class="sb-sidenav-menu-heading">الإدارة</div>
                
                <!-- إدارة المستخدمين -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseUsers" aria-expanded="false" aria-controls="collapseUsers">
                    <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                    إدارة المستخدمين
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseUsers" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/users/index.php">عرض المستخدمين</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/users/add.php">إضافة مستخدم</a>
                    </nav>
                </div>
                
                <!-- إدارة العملاء -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseClients" aria-expanded="false" aria-controls="collapseClients">
                    <div class="sb-nav-link-icon"><i class="fas fa-address-book"></i></div>
                    إدارة العملاء
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseClients" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/clients/index.php">عرض العملاء</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/clients/add.php">إضافة عميل</a>
                    </nav>
                </div>
                
                <!-- إدارة المبيعات -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseSales" aria-expanded="false" aria-controls="collapseSales">
                    <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                    إدارة المبيعات
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseSales" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/sales/index.php">عرض المبيعات</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/sales/add.php">إضافة مبيعة</a>
                    </nav>
                </div>
                
                <!-- إدارة الأهداف -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseTargets" aria-expanded="false" aria-controls="collapseTargets">
                    <div class="sb-nav-link-icon"><i class="fas fa-bullseye"></i></div>
                    إدارة الأهداف
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseTargets" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/targets/index.php">عرض الأهداف</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/targets/add.php">إضافة هدف</a>
                    </nav>
                </div>
                
                <!-- إدارة العمولات -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/commissions/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-coins"></i></div>
                    إدارة العمولات
                </a>
                
                <!-- التقارير -->
                <div class="sb-sidenav-menu-heading">التقارير</div>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseReports" aria-expanded="false" aria-controls="collapseReports">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div>
                    التقارير
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseReports" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/reports/sales.php">تقارير المبيعات</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/reports/commissions.php">تقارير العمولات</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/reports/targets.php">تقارير الأهداف</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/admin/reports/visits.php">تقارير الزيارات</a>
                    </nav>
                </div>
                
                <?php elseif ($_SESSION['role'] === 'manager'): ?>
                <!-- عناصر خاصة بالمدير -->
                <div class="sb-sidenav-menu-heading">الإدارة</div>
                
                <!-- إدارة المندوبين -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/salespeople/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-users"></i></div>
                    إدارة المندوبين
                </a>
                
                <!-- متابعة المبيعات -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/sales/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                    متابعة المبيعات
                </a>
                
                <!-- إدارة الأهداف -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/targets/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-bullseye"></i></div>
                    إدارة الأهداف
                </a>
                
                <!-- متابعة الزيارات -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/visits/index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-check"></i></div>
                    متابعة الزيارات
                </a>
                
                <!-- التقارير -->
                <div class="sb-sidenav-menu-heading">التقارير</div>
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseReports" aria-expanded="false" aria-controls="collapseReports">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div>
                    التقارير
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseReports" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/reports/sales.php">تقارير المبيعات</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/reports/performance.php">تقارير الأداء</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/manager/reports/visits.php">تقارير الزيارات</a>
                    </nav>
                </div>
                
                <?php elseif ($_SESSION['role'] === 'salesperson'): ?>
                <!-- عناصر خاصة بمندوب المبيعات -->
                <div class="sb-sidenav-menu-heading">إدارة المبيعات</div>
                
                <!-- المبيعات الشخصية -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/mysales.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-shopping-cart"></i></div>
                    مبيعاتي
                </a>
                
                <!-- العمولات المستحقة -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/mycommissions.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-coins"></i></div>
                    عمولاتي
                </a>
                
                <!-- الأهداف الشخصية -->
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/mytargets.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-bullseye"></i></div>
                    أهدافي
                </a>
                
                <!-- الزيارات الخارجية -->
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseVisits" aria-expanded="false" aria-controls="collapseVisits">
                    <div class="sb-nav-link-icon"><i class="fas fa-calendar-check"></i></div>
                    الزيارات الخارجية
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse" id="collapseVisits" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/visits/index.php">عرض الزيارات</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/visits/add.php">إضافة زيارة</a>
                        <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/visits/calendar.php">تقويم الزيارات</a>
                    </nav>
                </div>
                
                <!-- التقارير -->
                <div class="sb-sidenav-menu-heading">التقارير</div>
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/sales/reports.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-bar"></i></div>
                    تقاريري
                </a>
                <?php endif; ?>
                
                <!-- العناصر المشتركة في نهاية القائمة -->
                <div class="sb-sidenav-menu-heading">إعدادات</div>
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/auth/profile.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-user-cog"></i></div>
                    الملف الشخصي
                </a>
                <a class="nav-link" href="<?php echo URL_ROOT; ?>/auth/logout.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-sign-out-alt"></i></div>
                    تسجيل الخروج
                </a>
            </div>
        </div>
        <div class="sb-sidenav-footer">
            <div class="small">تم تسجيل الدخول كـ:</div>
            <?php 
                $role_name = '';
                switch ($_SESSION['role']) {
                    case 'admin':
                        $role_name = 'مدير النظام';
                        break;
                    case 'manager':
                        $role_name = 'مدير مبيعات';
                        break;
                    case 'salesperson':
                        $role_name = 'مندوب مبيعات';
                        break;
                }
                echo $_SESSION['full_name'] . ' (' . $role_name . ')';
            ?>
        </div>
    </nav>
</div>