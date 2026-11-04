<?php
require_once 'include/config.php';
if (empty($_SESSION['adminid'])) {
    header('location:login.php');
    exit();
}

$msg = '';
$error = '';

// 1. Add New Trainer
if (isset($_POST['action']) && $_POST['action'] === 'add_trainer') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $speciality = trim($_POST['speciality'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $exp = intval($_POST['experience_years'] ?? 1);
        $bio = trim($_POST['bio'] ?? '');
        $gymId = get_current_gym_id();

        if (empty($name)) {
            $error = 'Trainer name is required.';
        } else {
            $ins = $dbh->prepare("INSERT INTO tbl_trainers (name, speciality, mobile, email, experience_years, bio, status, gym_id) VALUES (:name, :spec, :mob, :email, :exp, :bio, 'active', :gym_id)");
            $ins->execute([
                ':name' => $name,
                ':spec' => $speciality,
                ':mob' => $mobile,
                ':email' => $email,
                ':exp' => $exp,
                ':bio' => $bio,
                ':gym_id' => $gymId
            ]);
            $msg = "Trainer added successfully!";
        }
    }
}

// 2. Assign Trainer to Member
if (isset($_POST['action']) && $_POST['action'] === 'assign_trainer') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch.';
    } else {
        $userId = intval($_POST['user_id'] ?? 0);
        $trainerId = intval($_POST['trainer_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $gymId = get_current_gym_id();

        if ($userId > 0 && $trainerId > 0) {
            $assign = $dbh->prepare("INSERT INTO tbl_member_trainers (user_id, trainer_id, assigned_date, status, notes, gym_id) VALUES (:uid, :tid, CURDATE(), 'active', :notes, :gym_id)");
            $assign->execute([':uid' => $userId, ':tid' => $trainerId, ':notes' => $notes, ':gym_id' => $gymId]);
            $msg = "Trainer assigned to member successfully!";
        } else {
            $error = "Please select both a valid member and trainer.";
        }
    }
}

// 3. Fetch Trainers with Athlete Counts
$trainers = $dbh->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM tbl_member_trainers mt WHERE mt.trainer_id = t.id AND mt.status = 'active') as active_athletes
    FROM tbl_trainers t
    ORDER BY t.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Fetch Active Assignments
$assignments = $dbh->query("
    SELECT mt.*, u.fname, u.lname, u.mobile as user_mobile, u.email as user_email, t.name as trainer_name, t.speciality
    FROM tbl_member_trainers mt
    JOIN tbluser u ON mt.user_id = u.id
    JOIN tbl_trainers t ON mt.trainer_id = t.id
    WHERE mt.status = 'active'
    ORDER BY mt.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// All Members for dropdown
$membersList = $dbh->query("SELECT id, fname, lname, mobile FROM tbluser ORDER BY fname ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Personal Trainers &amp; Workouts — Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="stylesheet" type="text/css" href="css/ios-admin-glass.css">
  <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .trainer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }
    .trainer-card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 20px;
      padding: 24px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .trainer-avatar {
      width: 60px;
      height: 60px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--color-primary), var(--color-accent));
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
    }
  </style>
</head>
<body class="app sidebar-mini rtl">
  <?php include 'include/header.php'; ?>
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include 'include/sidebar.php'; ?>

  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-user-secret" style="color: var(--color-primary); margin-right: 8px;"></i> Certified Trainers &amp; Coaching</h1>
        <p>Manage gym fitness instructors, assign personal trainers to members, and oversee conditioning programs</p>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home"></i></li>
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Trainers</li>
      </ul>
    </div>

    <!-- Alerts -->
    <?php if (!empty($msg)): ?>
      <div class="alert alert-success" style="border-radius: 12px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #fff;">
        <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger" style="border-radius: 12px; background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fff;">
        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
      <h3 style="font-size: 20px; font-weight: 700; margin: 0; color: #fff;">
        Fitness Coaches (<?php echo count($trainers); ?>)
      </h3>
      <div style="display: flex; gap: 8px;">
        <button type="button" class="btn btn-outline-info btn-sm" data-toggle="modal" data-target="#modalAssignTrainer" style="border-radius: 10px; font-weight: 600;">
          <i class="fa fa-link"></i> Assign Trainer to Member
        </button>
        <button type="button" class="ios-btn ios-btn-accent btn-sm" data-toggle="modal" data-target="#modalAddTrainer" style="border-radius: 10px;">
          <i class="fa fa-plus"></i> Add New Trainer
        </button>
      </div>
    </div>

    <!-- Trainers Grid -->
    <div class="trainer-grid">
      <?php foreach ($trainers as $t): ?>
      <div class="trainer-card">
        <div>
          <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 14px;">
            <div class="trainer-avatar">
              <?php echo strtoupper(substr($t['name'], 0, 1)); ?>
            </div>
            <div>
              <div style="font-weight: 700; color: #fff; font-size: 16px;"><?php echo htmlspecialchars($t['name']); ?></div>
              <div style="font-size: 12.5px; color: var(--color-primary); font-weight: 600;"><?php echo htmlspecialchars($t['speciality']); ?></div>
              <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo $t['experience_years']; ?>+ years experience</div>
            </div>
          </div>
          <p style="font-size: 13px; color: #cbd5e1; margin-bottom: 16px; line-height: 1.5;">
            <?php echo htmlspecialchars($t['bio'] ?? 'Dedicated personal coach ensuring strict biomechanics, progressive overload, and consistent client results.'); ?>
          </p>
        </div>
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 12px; margin-top: 8px;">
            <div>
              <span class="ios-badge ios-badge-success" style="font-size: 11px;">
                <i class="fa fa-users"></i> <?php echo $t['active_athletes']; ?> Athletes
              </span>
            </div>
            <div style="font-size: 12.5px; color: #94a3b8;">
              <i class="fa fa-phone"></i> <?php echo htmlspecialchars($t['mobile']); ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Active Coaching Assignments -->
    <div class="tile">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 18px; font-weight: 700; margin: 0; color: #fff;">
          <i class="fa fa-id-badge" style="color: var(--color-primary); margin-right: 6px;"></i> Active Client Coaching Roster
        </h3>
        <span class="ios-badge ios-badge-info"><?php echo count($assignments); ?> active clients</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover table-bordered" style="margin: 0;">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Member Name</th>
              <th>Assigned Trainer</th>
              <th>Coaching Speciality</th>
              <th>Assigned Date</th>
              <th>Goals &amp; Program Notes</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($assignments)): $cnt = 1; foreach ($assignments as $a): ?>
            <tr>
              <td style="color: var(--text-muted);"><?php echo $cnt++; ?></td>
              <td>
                <div style="font-weight: 700; color: #fff;">
                  <a href="member-profile.php?id=<?php echo $a['user_id']; ?>" style="color: inherit; text-decoration: none;">
                    <?php echo htmlspecialchars($a['fname'] . ' ' . $a['lname']); ?>
                  </a>
                </div>
                <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($a['user_mobile']); ?></div>
              </td>
              <td style="font-weight: 600; color: var(--color-primary);"><?php echo htmlspecialchars($a['trainer_name']); ?></td>
              <td style="color: #cbd5e1;"><?php echo htmlspecialchars($a['speciality']); ?></td>
              <td style="font-size: 13px; color: var(--text-muted);"><?php echo date('d M Y', strtotime($a['assigned_date'])); ?></td>
              <td style="font-size: 13px; color: #94a3b8;"><?php echo htmlspecialchars($a['notes'] ?? 'General fitness and endurance'); ?></td>
              <td style="text-align: right;">
                <a href="member-profile.php?id=<?php echo $a['user_id']; ?>" class="btn btn-sm btn-outline-light" style="border-radius: 8px;">
                  <i class="fa fa-eye"></i> View Profile
                </a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">No trainer assignments recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Modal: Add Trainer -->
  <div class="modal fade" id="modalAddTrainer" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: #181920; border: 1px solid rgba(255,255,255,0.15); border-radius: 18px; color: #fff;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
          <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-plus-circle text-primary"></i> Add New Fitness Coach</h5>
          <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="add_trainer">
          <div class="modal-body" style="row-gap: 12px; display: flex; flex-direction: column;">
            <div>
              <label style="font-size: 13px; font-weight: 600;">Coach Full Name</label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Rahul Sharma" style="border-radius: 10px;">
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Speciality / Expertise</label>
              <input type="text" name="speciality" class="form-control" required placeholder="e.g. Functional Strength, Powerlifting, HIIT" style="border-radius: 10px;">
            </div>
            <div class="row">
              <div class="col-md-6">
                <label style="font-size: 13px; font-weight: 600;">Contact Mobile</label>
                <input type="text" name="mobile" class="form-control" placeholder="Phone number" style="border-radius: 10px;">
              </div>
              <div class="col-md-6">
                <label style="font-size: 13px; font-weight: 600;">Experience (Years)</label>
                <input type="number" name="experience_years" class="form-control" value="3" min="0" style="border-radius: 10px;">
              </div>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="coach@fitforge.com" style="border-radius: 10px;">
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Bio / Philosophy</label>
              <textarea name="bio" class="form-control" rows="3" placeholder="Brief coaching credentials..." style="border-radius: 10px;"></textarea>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.08);">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" style="border-radius: 10px;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent" style="border-radius: 10px; padding: 8px 18px;">Save Coach</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: Assign Trainer -->
  <div class="modal fade" id="modalAssignTrainer" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content" style="background: #181920; border: 1px solid rgba(255,255,255,0.15); border-radius: 18px; color: #fff;">
        <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
          <h5 class="modal-title" style="font-weight: 700;"><i class="fa fa-link text-info"></i> Assign Personal Coach to Member</h5>
          <button type="button" class="close text-light" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form method="post">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="assign_trainer">
          <div class="modal-body" style="row-gap: 12px; display: flex; flex-direction: column;">
            <div>
              <label style="font-size: 13px; font-weight: 600;">Select Member</label>
              <select name="user_id" class="form-control" required style="border-radius: 10px;">
                <option value="">-- Choose Member --</option>
                <?php foreach ($membersList as $m): ?>
                  <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fname'] . ' ' . $m['lname']) . ' (' . $m['mobile'] . ')'; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Select Coach</label>
              <select name="trainer_id" class="form-control" required style="border-radius: 10px;">
                <option value="">-- Choose Coach --</option>
                <?php foreach ($trainers as $t): ?>
                  <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']) . ' — ' . htmlspecialchars($t['speciality']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Program Notes &amp; Target Goals</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Target weight loss, hypertrophy progression..." style="border-radius: 10px;"></textarea>
            </div>
          </div>
          <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.08);">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" style="border-radius: 10px;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-accent" style="border-radius: 10px; padding: 8px 18px;">Confirm Assignment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.2.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>
