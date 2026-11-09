<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>
<aside class="app-sidebar">
  <div class="sidebar-user">
    <?php 
    $sideLogo = function_exists('get_logo_url') ? get_logo_url() : '';
    $sideName = function_exists('get_setting') ? get_setting('gym_name', 'GYM MS') : 'GYM MS';
    $sideTagline = function_exists('get_setting') ? get_setting('gym_tagline', 'FITNESS CLUB') : 'FITNESS CLUB';
    ?>
    <div class="sidebar-user__logo">
      <?php if (!empty($sideLogo)): ?>
        <img src="<?php echo htmlspecialchars($sideLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo">
      <?php else: ?>
        <i class="fa fa-bolt" style="color: var(--color-primary); font-size: 16px;"></i>
      <?php endif; ?>
    </div>
    <div class="sidebar-user__info">
      <div class="sidebar-user__name"><?php echo htmlspecialchars($sideName, ENT_QUOTES, 'UTF-8'); ?></div>
      <div style="margin-top: 3px;">
        <?php echo function_exists('get_role_badge') ? get_role_badge(current_admin_role()) : ''; ?>
      </div>
    </div>
    <!-- Mobile Sidebar Close Button -->
    <button type="button" class="app-sidebar__close" data-toggle="sidebar" aria-label="Close Sidebar" title="Close Sidebar">
      <i class="fa fa-times"></i>
    </button>
  </div>
  <ul class="app-menu">
    <?php
    $adminRole = function_exists('current_admin_role') ? current_admin_role() : 'owner';
    $pendingStaffCount = ($adminRole === 'owner' && function_exists('get_pending_staff_count') && isset($dbh)) ? get_pending_staff_count($dbh) : 0;
    ?>

    <!-- 1. Dashboard -->
    <li>
      <a class="app-menu__item <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>" href="index.php">
        <i class="app-menu__icon fa fa-tachometer"></i>
        <span class="app-menu__label">Dashboard</span>
      </a>
    </li>

    <!-- Owner Only: Staff & Approvals -->
    <?php if ($adminRole === 'owner'): ?>
      <li>
        <a class="app-menu__item <?php echo ($currentPage == 'staff.php') ? 'active' : ''; ?>" href="staff.php">
          <i class="app-menu__icon fa fa-user-secret" style="color: #f59e0b;"></i>
          <span class="app-menu__label">Staff &amp; Approvals</span>
          <?php if ($pendingStaffCount > 0): ?>
            <span class="badge badge-danger" style="margin-left: auto; border-radius: 10px; font-size: 11px;"><?php echo $pendingStaffCount; ?></span>
          <?php endif; ?>
        </a>
      </li>
    <?php endif; ?>

    <!-- 2. Attendance & QR Check-in (All Roles) -->
    <li class="treeview <?php echo in_array($currentPage, ['attendance.php', 'gym-qr.php']) ? 'is-expanded' : ''; ?>">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon fa fa-qrcode" style="color: #10b981;"></i>
        <span class="app-menu__label">Attendance &amp; Check-In</span>
        <i class="treeview-indicator fa fa-angle-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item <?php echo ($currentPage == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php"><i class="icon fa fa-users"></i> Live Floor &amp; Staff Scanner</a></li>
        <li><a class="treeview-item <?php echo ($currentPage == 'gym-qr.php') ? 'active' : ''; ?>" href="gym-qr.php"><i class="icon fa fa-print"></i> Gym QR Poster &amp; Station</a></li>
      </ul>
    </li>

    <!-- 3. Members & Subs (Owner, Manager, Trainer, Floor Staff lookup) -->
    <li class="treeview <?php echo in_array($currentPage, ['members.php', 'member-profile.php', 'member-create.php', 'memberships.php', 'trainers.php']) ? 'is-expanded' : ''; ?>">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon fa fa-users"></i>
        <span class="app-menu__label"><?php echo ($adminRole === 'trainer') ? 'Clients &amp; Members' : 'Members &amp; Subs'; ?></span>
        <i class="treeview-indicator fa fa-angle-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item <?php echo ($currentPage == 'members.php') ? 'active' : ''; ?>" href="members.php"><i class="icon fa fa-address-book-o"></i> Member Directory</a></li>
        <?php if (in_array($adminRole, ['owner', 'manager'])): ?>
          <li><a class="treeview-item <?php echo ($currentPage == 'member-create.php') ? 'active' : ''; ?>" href="member-create.php"><i class="icon fa fa-user-plus"></i> Add New Member</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'memberships.php') ? 'active' : ''; ?>" href="memberships.php"><i class="icon fa fa-id-card-o"></i> All Memberships</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'trainers.php') ? 'active' : ''; ?>" href="trainers.php"><i class="icon fa fa-user-secret"></i> Trainers &amp; Coaching</a></li>
        <?php elseif ($adminRole === 'trainer'): ?>
          <li><a class="treeview-item <?php echo ($currentPage == 'trainers.php') ? 'active' : ''; ?>" href="trainers.php"><i class="icon fa fa-user-secret"></i> Coaches Roster</a></li>
        <?php endif; ?>
      </ul>
    </li>

    <!-- 4. Membership Plans (Owner & Manager) -->
    <?php if (in_array($adminRole, ['owner', 'manager'])): ?>
      <li class="treeview <?php echo in_array($currentPage, ['plans.php', 'add-post.php', 'manage-post.php', 'edit-post.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-id-card"></i>
          <span class="app-menu__label">Membership Plans</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'plans.php') ? 'active' : ''; ?>" href="plans.php"><i class="icon fa fa-list"></i> Manage Plans</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'add-post.php') ? 'active' : ''; ?>" href="add-post.php"><i class="icon fa fa-plus"></i> Add New Plan</a></li>
        </ul>
      </li>

      <!-- 5. Categories & Types (Owner & Manager) -->
      <li class="treeview <?php echo in_array($currentPage, ['categories.php', 'add-category.php', 'add-package.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-tags"></i>
          <span class="app-menu__label">Categories</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'categories.php') ? 'active' : ''; ?>" href="categories.php"><i class="icon fa fa-cubes"></i> Categories &amp; Types</a></li>
        </ul>
      </li>

      <!-- 6. Payments & Bookings (Owner & Manager) -->
      <li class="treeview <?php echo in_array($currentPage, ['bookings.php', 'booking-history.php', 'new-bookings.php', 'partial-payment-bookings.php', 'full-payment-bookings.php', 'booking-history-details.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-credit-card"></i>
          <span class="app-menu__label">Payments &amp; Bookings</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'bookings.php') ? 'active' : ''; ?>" href="bookings.php"><i class="icon fa fa-calendar-check-o"></i> Bookings Ledger</a></li>
          <li><a class="treeview-item" href="bookings.php?filter=unpaid"><i class="icon fa fa-bell-o"></i> Pending Payments</a></li>
          <li><a class="treeview-item" href="bookings.php?filter=partial"><i class="icon fa fa-hourglass-half"></i> Partial Payments</a></li>
          <li><a class="treeview-item" href="bookings.php?filter=paid"><i class="icon fa fa-check-circle"></i> Fully Paid</a></li>
        </ul>
      </li>

      <!-- 7. Reports & Analytics (Owner & Manager) -->
      <li class="treeview <?php echo in_array($currentPage, ['reports.php', 'report-expirations.php', 'report-dues.php', 'report-booking.php', 'report-registration.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-bar-chart"></i>
          <span class="app-menu__label">Reports</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>" href="reports.php"><i class="icon fa fa-tachometer"></i> Reports Hub</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'report-expirations.php') ? 'active' : ''; ?>" href="report-expirations.php"><i class="icon fa fa-clock-o"></i> Expiring Passes</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'report-dues.php') ? 'active' : ''; ?>" href="report-dues.php"><i class="icon fa fa-money"></i> Outstanding Dues</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'report-booking.php') ? 'active' : ''; ?>" href="report-booking.php"><i class="icon fa fa-line-chart"></i> Sales &amp; Bookings</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'report-registration.php') ? 'active' : ''; ?>" href="report-registration.php"><i class="icon fa fa-user-plus"></i> Member Sign-ups</a></li>
        </ul>
      </li>
    <?php endif; ?>

    <!-- 8. Website Management (Owner Only) -->
    <?php if ($adminRole === 'owner'): ?>
      <li class="treeview <?php echo in_array($currentPage, ['website-branding.php', 'website-content.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-globe"></i>
          <span class="app-menu__label">Website Management</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'website-branding.php') ? 'active' : ''; ?>" href="website-branding.php"><i class="icon fa fa-paint-brush"></i> Brand &amp; Contact</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'website-content.php') ? 'active' : ''; ?>" href="website-content.php"><i class="icon fa fa-file-text-o"></i> Hero, About &amp; Features</a></li>
        </ul>
      </li>

      <!-- 9. Settings (Owner Only) -->
      <li class="treeview <?php echo in_array($currentPage, ['settings.php', 'theme-settings.php', 'notifications-hub.php', 'change-password.php', 'profile.php']) ? 'is-expanded' : ''; ?>">
        <a class="app-menu__item" href="#" data-toggle="treeview">
          <i class="app-menu__icon fa fa-cogs"></i>
          <span class="app-menu__label">Settings</span>
          <i class="treeview-indicator fa fa-angle-right"></i>
        </a>
        <ul class="treeview-menu">
          <li><a class="treeview-item <?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>" href="settings.php"><i class="icon fa fa-sliders"></i> System Settings</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'notifications-hub.php') ? 'active' : ''; ?>" href="notifications-hub.php"><i class="icon fa fa-whatsapp"></i> Alerts &amp; WhatsApp</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'theme-settings.php') ? 'active' : ''; ?>" href="theme-settings.php"><i class="icon fa fa-magic"></i> Theme &amp; Appearance</a></li>
          <li><a class="treeview-item <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>" href="profile.php"><i class="icon fa fa-user"></i> Admin Profile</a></li>
        </ul>
      </li>
    <?php else: ?>
      <!-- Profile for Non-Owners -->
      <li>
        <a class="app-menu__item <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
          <i class="app-menu__icon fa fa-user"></i>
          <span class="app-menu__label">My Profile &amp; Pass</span>
        </a>
      </li>
    <?php endif; ?>

    <li style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px; margin-bottom: 70px;">
      <a class="app-menu__item" href="../index.php" target="_blank">
        <i class="app-menu__icon fa fa-external-link" style="color: var(--color-primary);"></i>
        <span class="app-menu__label">View Public Site</span>
      </a>
    </li>
  </ul>
</aside>

<!-- Admin Mobile Bottom Command Dock -->
<nav class="app-mobile-dock" aria-label="Admin Navigation Bar">
  <a href="index.php" class="app-dock-item <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
    <i class="fa fa-tachometer"></i>
    <span>Dashboard</span>
  </a>
  <a href="members.php" class="app-dock-item <?php echo in_array($currentPage, ['members.php', 'member-profile.php', 'member-create.php']) ? 'active' : ''; ?>">
    <i class="fa fa-users"></i>
    <span>Members</span>
  </a>
  <a href="booking-history.php" class="app-dock-item <?php echo in_array($currentPage, ['booking-history.php', 'booking-history-details.php']) ? 'active' : ''; ?>">
    <i class="fa fa-credit-card"></i>
    <span>Payments</span>
  </a>
  <a href="plans.php" class="app-dock-item <?php echo in_array($currentPage, ['plans.php', 'plan-edit.php', 'categories.php']) ? 'active' : ''; ?>">
    <i class="fa fa-id-card"></i>
    <span>Plans</span>
  </a>
  <a href="#" class="app-dock-item" data-toggle="sidebar">
    <i class="fa fa-bars"></i>
    <span>Menu</span>
  </a>
</nav>
