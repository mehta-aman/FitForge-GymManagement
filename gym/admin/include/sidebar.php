<?php 
$currentPage = basename($_SERVER['PHP_SELF']); 
?>
<aside class="app-sidebar">
  <div class="sidebar-user" style="padding: 18px 20px 14px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
    <?php 
    $sideLogo = function_exists('get_logo_url') ? get_logo_url() : '';
    $sideName = function_exists('get_setting') ? get_setting('gym_name', 'GYM MS') : 'GYM MS';
    $sideTagline = function_exists('get_setting') ? get_setting('gym_tagline', 'FITNESS CLUB') : 'FITNESS CLUB';
    ?>
    <?php if (!empty($sideLogo)): ?>
      <img src="<?php echo htmlspecialchars($sideLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="width: 38px; height: 38px; border-radius: 10px; object-fit: contain; background: rgba(255,255,255,0.06); padding: 3px; border: 1px solid rgba(255,255,255,0.12);">
    <?php else: ?>
      <div style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--color-primary), var(--color-accent)); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px;">
        <i class="fa fa-bolt"></i>
      </div>
    <?php endif; ?>
    <div style="overflow: hidden; flex: 1;">
      <div style="font-weight: 700; color: #fff; font-size: 14.5px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;"><?php echo htmlspecialchars($sideName, ENT_QUOTES, 'UTF-8'); ?></div>
      <div style="font-size: 10px; color: var(--color-primary); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;"><?php echo htmlspecialchars($sideTagline, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <!-- Mobile Sidebar Close Button -->
    <button type="button" class="app-sidebar__close" data-toggle="sidebar" aria-label="Close Sidebar">
      <i class="fa fa-times"></i>
    </button>
  </div>
  <ul class="app-menu">
    <!-- 1. Dashboard -->
    <li>
      <a class="app-menu__item <?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>" href="index.php">
        <i class="app-menu__icon fa fa-tachometer"></i>
        <span class="app-menu__label">Dashboard</span>
      </a>
    </li>

    <!-- 2. Members & Memberships -->
    <li class="treeview <?php echo in_array($currentPage, ['members.php', 'member-profile.php', 'member-create.php', 'memberships.php']) ? 'is-expanded' : ''; ?>">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon fa fa-users"></i>
        <span class="app-menu__label">Members &amp; Subs</span>
        <i class="treeview-indicator fa fa-angle-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item <?php echo ($currentPage == 'members.php') ? 'active' : ''; ?>" href="members.php"><i class="icon fa fa-address-book-o"></i> Member Directory</a></li>
        <li><a class="treeview-item <?php echo ($currentPage == 'member-create.php') ? 'active' : ''; ?>" href="member-create.php"><i class="icon fa fa-user-plus"></i> Add New Member</a></li>
        <li><a class="treeview-item <?php echo ($currentPage == 'memberships.php') ? 'active' : ''; ?>" href="memberships.php"><i class="icon fa fa-id-card-o"></i> All Memberships</a></li>
      </ul>
    </li>

    <!-- 3. Membership Plans -->
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

    <!-- 4. Categories & Types -->
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

    <!-- 5. Payments & Bookings -->
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

    <!-- 6. Reports & Analytics -->
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

    <!-- 7. Website Management -->
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

    <!-- 8. Settings & Themes -->
    <li class="treeview <?php echo in_array($currentPage, ['settings.php', 'theme-settings.php', 'change-password.php', 'profile.php']) ? 'is-expanded' : ''; ?>">
      <a class="app-menu__item" href="#" data-toggle="treeview">
        <i class="app-menu__icon fa fa-cogs"></i>
        <span class="app-menu__label">Settings</span>
        <i class="treeview-indicator fa fa-angle-right"></i>
      </a>
      <ul class="treeview-menu">
        <li><a class="treeview-item <?php echo ($currentPage == 'settings.php') ? 'active' : ''; ?>" href="settings.php"><i class="icon fa fa-sliders"></i> System Settings</a></li>
        <li><a class="treeview-item <?php echo ($currentPage == 'theme-settings.php') ? 'active' : ''; ?>" href="theme-settings.php"><i class="icon fa fa-magic"></i> Theme &amp; Appearance</a></li>
        <li><a class="treeview-item <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>" href="profile.php"><i class="icon fa fa-user"></i> Admin Profile</a></li>
      </ul>
    </li>

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
