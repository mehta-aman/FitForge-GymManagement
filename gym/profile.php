<?php
require_once 'include/config.php';
require_once 'include/fitness_metrics.php';

if (empty($_SESSION['uid'])) {
    header('location:login.php');
    exit();
}

$userId = intval($_SESSION['uid']);
$gymId = get_current_gym_id();
$flashSuccess = '';
$flashError = '';

// ==============================================================================
// 1. POST ACTION CONTROLLERS
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flashError = 'Security validation failed (CSRF token mismatch). Please try again.';
    } else {
        $action = trim($_POST['action'] ?? '');

        // ----------------------------------------------------------------------
        // Action A: Update Personal Info & Fitness Preferences
        // ----------------------------------------------------------------------
        if ($action === 'update_profile') {
            $fname = trim($_POST['fname'] ?? '');
            $lname = trim($_POST['lname'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            $dob = !empty($_POST['dob']) ? trim($_POST['dob']) : null;
            $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other', 'prefer_not_to_say']) ? $_POST['gender'] : 'prefer_not_to_say';
            $height = (!empty($_POST['height']) && floatval($_POST['height']) > 0) ? floatval($_POST['height']) : null;
            $fitnessGoal = in_array($_POST['fitness_goal'] ?? '', ['fat_loss', 'muscle_gain', 'strength', 'endurance', 'general_fitness', 'maintenance']) ? $_POST['fitness_goal'] : 'general_fitness';
            $activityLevel = in_array($_POST['activity_level'] ?? '', ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active']) ? $_POST['activity_level'] : 'moderately_active';
            $unitPref = in_array($_POST['unit_preference'] ?? '', ['metric', 'imperial']) ? $_POST['unit_preference'] : 'metric';
            $targetWeight = (!empty($_POST['target_weight']) && floatval($_POST['target_weight']) > 0) ? floatval($_POST['target_weight']) : null;
            $targetDate = !empty($_POST['target_date']) ? trim($_POST['target_date']) : null;
            $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
            $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');

            // Handle Photo Upload
            $profilePhotoPath = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $uploadRes = handle_image_upload($_FILES['profile_photo'], 'profiles', 5);
                if ($uploadRes['success']) {
                    $profilePhotoPath = $uploadRes['path'];
                } else {
                    $flashError = 'Photo upload error: ' . $uploadRes['error'];
                }
            }

            if (empty($fname)) {
                $flashError = 'First name is required.';
            } else {
                $sql = "UPDATE tbluser SET 
                    fname = :fname,
                    lname = :lname,
                    mobile = :mobile,
                    city = :city,
                    state = :state,
                    address = :address,
                    dob = :dob,
                    gender = :gender,
                    height = :height,
                    fitness_goal = :fitness_goal,
                    activity_level = :activity_level,
                    unit_preference = :unit_preference,
                    target_weight = :target_weight,
                    target_date = :target_date,
                    emergency_contact_name = :emergency_contact_name,
                    emergency_contact_phone = :emergency_contact_phone";

                $params = [
                    ':fname' => $fname,
                    ':lname' => $lname,
                    ':mobile' => $mobile,
                    ':city' => $city,
                    ':state' => $state,
                    ':address' => $address,
                    ':dob' => $dob,
                    ':gender' => $gender,
                    ':height' => $height,
                    ':fitness_goal' => $fitnessGoal,
                    ':activity_level' => $activityLevel,
                    ':unit_preference' => $unitPref,
                    ':target_weight' => $targetWeight,
                    ':target_date' => $targetDate,
                    ':emergency_contact_name' => $emergencyName,
                    ':emergency_contact_phone' => $emergencyPhone,
                    ':uid' => $userId
                ];

                if ($profilePhotoPath !== null) {
                    $sql .= ", profile_photo = :profile_photo";
                    $params[':profile_photo'] = $profilePhotoPath;
                }

                $sql .= " WHERE id = :uid";
                $stmt = $dbh->prepare($sql);
                $stmt->execute($params);

                $_SESSION['name'] = $fname;
                $flashSuccess = 'Profile information and fitness preferences updated successfully.';
            }
        }

        // ----------------------------------------------------------------------
        // Action B: Add Body Measurement Record
        // ----------------------------------------------------------------------
        elseif ($action === 'add_measurement') {
            $inputUnit = in_array($_POST['input_unit'] ?? '', ['metric', 'imperial']) ? $_POST['input_unit'] : 'metric';
            $measureDate = !empty($_POST['measurement_date']) ? trim($_POST['measurement_date']) : date('Y-m-d');
            
            // Raw inputs
            $rawWeight = floatval($_POST['weight'] ?? 0);
            $rawHeight = !empty($_POST['height_input']) ? floatval($_POST['height_input']) : null;
            $rawWaist = floatval($_POST['waist'] ?? 0);
            $rawNeck = !empty($_POST['neck']) ? floatval($_POST['neck']) : null;
            $rawChest = !empty($_POST['chest']) ? floatval($_POST['chest']) : null;
            $rawShoulders = !empty($_POST['shoulders']) ? floatval($_POST['shoulders']) : null;
            $rawHip = !empty($_POST['hip']) ? floatval($_POST['hip']) : null;
            $rawArmL = !empty($_POST['arm_left']) ? floatval($_POST['arm_left']) : null;
            $rawArmR = !empty($_POST['arm_right']) ? floatval($_POST['arm_right']) : null;
            $rawForearmL = !empty($_POST['forearm_left']) ? floatval($_POST['forearm_left']) : null;
            $rawForearmR = !empty($_POST['forearm_right']) ? floatval($_POST['forearm_right']) : null;
            $rawThighL = !empty($_POST['thigh_left']) ? floatval($_POST['thigh_left']) : null;
            $rawThighR = !empty($_POST['thigh_right']) ? floatval($_POST['thigh_right']) : null;
            $rawCalfL = !empty($_POST['calf_left']) ? floatval($_POST['calf_left']) : null;
            $rawCalfR = !empty($_POST['calf_right']) ? floatval($_POST['calf_right']) : null;
            
            $bodyFat = (!empty($_POST['body_fat_pct']) && floatval($_POST['body_fat_pct']) > 0) ? floatval($_POST['body_fat_pct']) : null;
            $bodyFatSource = in_array($_POST['body_fat_source'] ?? '', ['manual', 'scale_device', 'caliper', 'dexa', 'other']) ? $_POST['body_fat_source'] : 'manual';
            $notes = trim($_POST['notes'] ?? '');

            // Convert to canonical metric (kg, cm) if imperial was used
            if ($inputUnit === 'imperial') {
                $weightKg = round(fm_lb_to_kg($rawWeight), 2);
                $heightCm = $rawHeight ? round(fm_in_to_cm($rawHeight), 2) : null;
                $waistCm = round(fm_in_to_cm($rawWaist), 2);
                $neckCm = $rawNeck ? round(fm_in_to_cm($rawNeck), 2) : null;
                $chestCm = $rawChest ? round(fm_in_to_cm($rawChest), 2) : null;
                $shouldersCm = $rawShoulders ? round(fm_in_to_cm($rawShoulders), 2) : null;
                $hipCm = $rawHip ? round(fm_in_to_cm($rawHip), 2) : null;
                $armLCm = $rawArmL ? round(fm_in_to_cm($rawArmL), 2) : null;
                $armRCm = $rawArmR ? round(fm_in_to_cm($rawArmR), 2) : null;
                $forearmLCm = $rawForearmL ? round(fm_in_to_cm($rawForearmL), 2) : null;
                $forearmRCm = $rawForearmR ? round(fm_in_to_cm($rawForearmR), 2) : null;
                $thighLCm = $rawThighL ? round(fm_in_to_cm($rawThighL), 2) : null;
                $thighRCm = $rawThighR ? round(fm_in_to_cm($rawThighR), 2) : null;
                $calfLCm = $rawCalfL ? round(fm_in_to_cm($rawCalfL), 2) : null;
                $calfRCm = $rawCalfR ? round(fm_in_to_cm($rawCalfR), 2) : null;
            } else {
                $weightKg = round($rawWeight, 2);
                $heightCm = $rawHeight ? round($rawHeight, 2) : null;
                $waistCm = round($rawWaist, 2);
                $neckCm = $rawNeck ? round($rawNeck, 2) : null;
                $chestCm = $rawChest ? round($rawChest, 2) : null;
                $shouldersCm = $rawShoulders ? round($rawShoulders, 2) : null;
                $hipCm = $rawHip ? round($rawHip, 2) : null;
                $armLCm = $rawArmL ? round($rawArmL, 2) : null;
                $armRCm = $rawArmR ? round($rawArmR, 2) : null;
                $forearmLCm = $rawForearmL ? round($rawForearmL, 2) : null;
                $forearmRCm = $rawForearmR ? round($rawForearmR, 2) : null;
                $thighLCm = $rawThighL ? round($rawThighL, 2) : null;
                $thighRCm = $rawThighR ? round($rawThighR, 2) : null;
                $calfLCm = $rawCalfL ? round($rawCalfL, 2) : null;
                $calfRCm = $rawCalfR ? round($rawCalfR, 2) : null;
            }

            // Validation
            if ($weightKg < 20 || $weightKg > 350) {
                $flashError = 'Body weight must be a realistic value between 20 kg (44 lb) and 350 kg (770 lb).';
            } elseif ($waistCm < 30 || $waistCm > 250) {
                $flashError = 'Waist circumference must be between 30 cm (12 in) and 250 cm (98 in).';
            } else {
                $stmtIns = $dbh->prepare("INSERT INTO tbl_member_measurements 
                    (user_id, gym_id, measurement_date, weight_kg, height_cm, waist_cm, neck_cm, chest_cm, shoulders_cm, hip_cm, arm_left_cm, arm_right_cm, forearm_left_cm, forearm_right_cm, thigh_left_cm, thigh_right_cm, calf_left_cm, calf_right_cm, body_fat_pct, body_fat_source, input_unit, notes)
                    VALUES 
                    (:uid, :gym_id, :m_date, :w_kg, :h_cm, :waist, :neck, :chest, :shoulders, :hip, :arm_l, :arm_r, :fa_l, :fa_r, :thigh_l, :thigh_r, :calf_l, :calf_r, :bf_pct, :bf_src, :in_unit, :notes)");
                
                $stmtIns->execute([
                    ':uid' => $userId,
                    ':gym_id' => $gymId,
                    ':m_date' => $measureDate,
                    ':w_kg' => $weightKg,
                    ':h_cm' => $heightCm,
                    ':waist' => $waistCm,
                    ':neck' => $neckCm,
                    ':chest' => $chestCm,
                    ':shoulders' => $shouldersCm,
                    ':hip' => $hipCm,
                    ':arm_l' => $armLCm,
                    ':arm_r' => $armRCm,
                    ':fa_l' => $forearmLCm,
                    ':fa_r' => $forearmRCm,
                    ':thigh_l' => $thighLCm,
                    ':thigh_r' => $thighRCm,
                    ':calf_l' => $calfLCm,
                    ':calf_r' => $calfRCm,
                    ':bf_pct' => $bodyFat,
                    ':bf_src' => $bodyFatSource,
                    ':in_unit' => $inputUnit,
                    ':notes' => $notes
                ]);

                // Update height snapshot in tbluser if entered
                if ($heightCm && $heightCm > 50) {
                    $dbh->prepare("UPDATE tbluser SET height = :h WHERE id = :uid AND (height IS NULL OR height = 0)")->execute([':h' => $heightCm, ':uid' => $userId]);
                }

                $flashSuccess = "Measurement entry logged successfully for " . date('d M Y', strtotime($measureDate)) . "!";
            }
        }

        // ----------------------------------------------------------------------
        // Action C: Delete Measurement Record (Strict Ownership Protected)
        // ----------------------------------------------------------------------
        elseif ($action === 'delete_measurement') {
            $mId = intval($_POST['measurement_id'] ?? 0);
            if ($mId > 0) {
                $delStmt = $dbh->prepare("DELETE FROM tbl_member_measurements WHERE id = :id AND user_id = :uid");
                $delStmt->execute([':id' => $mId, ':uid' => $userId]);
                $flashSuccess = 'Measurement record deleted successfully.';
            }
        }
    }
}

// ==============================================================================
// 2. DATA QUERIES & REPRODUCIBLE METRICS
// ==============================================================================

// A. Member Profile Info
$userStmt = $dbh->prepare("SELECT * FROM tbluser WHERE id = :id");
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('location:logout.php');
    exit();
}

$unitPref = $user['unit_preference'] ?? 'metric';
$memberCode = "MEM-" . str_pad($userId, 4, '0', STR_PAD_LEFT);
$memberAge = fm_calculate_age($user['dob'] ?? null);

// B. Active Membership Pass
$memStmt = $dbh->prepare("
    SELECT m.*, p.titlename as plan_name, p.PackageDuratiobn, p.Price,
    DATEDIFF(m.expiry_date, CURDATE()) as days_left
    FROM tbl_memberships m
    LEFT JOIN tbladdpackage p ON m.plan_id = p.id
    WHERE m.user_id = :id
    ORDER BY m.id DESC LIMIT 1
");
$memStmt->execute([':id' => $userId]);
$membership = $memStmt->fetch(PDO::FETCH_ASSOC);

// C. Chronological Measurements Ledger
$measureStmt = $dbh->prepare("
    SELECT * FROM tbl_member_measurements 
    WHERE user_id = :uid 
    ORDER BY measurement_date DESC, id DESC
");
$measureStmt->execute([':uid' => $userId]);
$measurements = $measureStmt->fetchAll(PDO::FETCH_ASSOC);

$totalMeasurements = count($measurements);
$latestM = $measurements[0] ?? null;
$initialM = $measurements[$totalMeasurements - 1] ?? null;

// Determine baseline height & weight
$currentWeightKg = $latestM ? floatval($latestM['weight_kg']) : null;
$currentHeightCm = ($latestM && !empty($latestM['height_cm'])) ? floatval($latestM['height_cm']) : (!empty($user['height']) ? floatval($user['height']) : null);
$currentWaistCm = $latestM ? floatval($latestM['waist_cm']) : null;
$currentHipCm = ($latestM && !empty($latestM['hip_cm'])) ? floatval($latestM['hip_cm']) : null;
$currentBodyFat = ($latestM && !empty($latestM['body_fat_pct'])) ? floatval($latestM['body_fat_pct']) : null;

// Weight Change (Delta)
$weightDeltaKg = ($latestM && $initialM && $totalMeasurements > 1) ? round($latestM['weight_kg'] - $initialM['weight_kg'], 1) : 0;
$waistDeltaCm = ($latestM && $initialM && $totalMeasurements > 1) ? round($latestM['waist_cm'] - $initialM['waist_cm'], 1) : 0;

// Dynamic Metrics via Calculation Service
$bmiData = ($currentWeightKg && $currentHeightCm) ? fm_calculate_bmi($currentWeightKg, $currentHeightCm, $memberAge) : null;
$bmrData = ($currentWeightKg && $currentHeightCm && $memberAge) ? fm_calculate_bmr_mifflin($currentWeightKg, $currentHeightCm, $memberAge, $user['gender'] ?? null) : null;
$tdeeData = ($bmrData && $bmrData['status'] === 'calculated') ? fm_calculate_tdee($bmrData['bmr'], $user['activity_level'] ?? 'moderately_active') : null;
$calorieTargetData = ($tdeeData && $tdeeData['status'] === 'calculated') ? fm_calculate_calorie_target($tdeeData['tdee'], $user['fitness_goal'] ?? 'general_fitness', $user['gender'] ?? null, $memberAge) : null;
$macroData = ($calorieTargetData && $calorieTargetData['status'] === 'calculated' && $currentWeightKg) ? fm_calculate_macronutrients($calorieTargetData['target_calories'], $currentWeightKg, $user['fitness_goal'] ?? 'general_fitness') : null;
$hydrationData = $currentWeightKg ? fm_calculate_hydration_target($currentWeightKg, $user['activity_level'] ?? 'moderately_active') : null;
$whtrData = ($currentWaistCm && $currentHeightCm) ? fm_calculate_waist_to_height_ratio($currentWaistCm, $currentHeightCm) : null;
$whrData = ($currentWaistCm && $currentHipCm) ? fm_calculate_waist_to_hip_ratio($currentWaistCm, $currentHipCm, $user['gender'] ?? null) : null;
$profileCompletion = fm_calculate_profile_completion($user, $totalMeasurements);

// Prepare Chart.js Time-Series Data (Reverse chronologically for left-to-right graphs)
$chartRecords = array_reverse($measurements);
$chartLabels = json_encode(array_map(fn($m) => date('d M', strtotime($m['measurement_date'])), $chartRecords));
$chartWeights = json_encode(array_map(fn($m) => ($unitPref === 'imperial') ? round(fm_kg_to_lb($m['weight_kg']), 1) : floatval($m['weight_kg']), $chartRecords));
$chartWaists = json_encode(array_map(fn($m) => ($unitPref === 'imperial') ? round(fm_cm_to_in($m['waist_cm']), 1) : floatval($m['waist_cm']), $chartRecords));
$chartBmi = json_encode(array_map(function($m) use ($currentHeightCm, $memberAge) {
    $h = (!empty($m['height_cm'])) ? floatval($m['height_cm']) : $currentHeightCm;
    if ($h && $h > 0) {
        $res = fm_calculate_bmi($m['weight_kg'], $h, $memberAge);
        return $res['bmi'];
    }
    return null;
}, $chartRecords));

$gymName = get_setting('gym_name', 'Golden Eagle Gym');
$gymTagline = get_setting('gym_tagline', 'FITNESS CLUB');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Profile &amp; Fitness Metrics — <?php echo htmlspecialchars($gymName); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php echo render_favicon_tags(); ?>
  <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
  <link rel="stylesheet" type="text/css" href="css/font-awesome.min.css">
  <link rel="stylesheet" type="text/css" href="css/ios-glass.css">
  <style>
    body {
      background: radial-gradient(circle at 10% 10%, #1e1b4b 0%, #09090b 55%, #030712 100%);
      color: #f8fafc;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
    }
    
    /* Top Profile Header Card */
    .profile-hero-card {
      background: rgba(15, 23, 42, 0.75);
      backdrop-filter: blur(28px) saturate(190%);
      -webkit-backdrop-filter: blur(28px) saturate(190%);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 24px;
      padding: 28px 32px;
      margin-bottom: 24px;
      box-shadow: 0 16px 36px rgba(0, 0, 0, 0.45);
    }
    
    .profile-avatar-box {
      width: 90px;
      height: 90px;
      border-radius: 22px;
      background: linear-gradient(135deg, var(--color-primary, #38bdf8), var(--color-accent, #6366f1));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      font-weight: 800;
      color: #fff;
      overflow: hidden;
      border: 2px solid rgba(255, 255, 255, 0.2);
      flex-shrink: 0;
      box-shadow: 0 8px 24px rgba(56, 189, 248, 0.25);
    }
    
    .profile-avatar-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    /* Tab Navigation Chips (Horizontal slider on mobile) */
    .profile-nav-tabs {
      display: flex;
      gap: 8px;
      overflow-x: auto;
      white-space: nowrap;
      padding-bottom: 10px;
      margin-bottom: 24px;
      -webkit-overflow-scrolling: touch;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    .profile-nav-tabs::-webkit-scrollbar {
      height: 4px;
    }
    .profile-nav-tabs::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.15);
      border-radius: 4px;
    }
    
    .profile-tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      border-radius: 12px;
      font-size: 13.5px;
      font-weight: 600;
      color: #94a3b8;
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      text-decoration: none !important;
      cursor: pointer;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }
    
    .profile-tab-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.09);
      border-color: rgba(255, 255, 255, 0.16);
    }
    
    .profile-tab-btn.active {
      color: #fff;
      background: linear-gradient(135deg, rgba(56, 189, 248, 0.2) 0%, rgba(99, 102, 241, 0.25) 100%);
      border-color: rgba(56, 189, 248, 0.45);
      box-shadow: 0 4px 16px rgba(56, 189, 248, 0.18);
    }
    
    /* Metric & KPI Cards */
    .metric-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 20px 22px;
      margin-bottom: 20px;
      height: calc(100% - 20px);
      transition: transform 0.2s ease, border-color 0.2s ease;
    }
    
    .metric-card:hover {
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.18);
    }
    
    .metric-icon-wrap {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      margin-bottom: 12px;
      flex-shrink: 0;
    }
    
    .tab-content-panel {
      display: none;
      animation: fadeIn 0.25s ease;
    }
    .tab-content-panel.active {
      display: block;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(6px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Print Stylesheet for Fitness Report */
    @media print {
      body {
        background: #ffffff !important;
        color: #000000 !important;
      }
      .ios-glass-navbar, .profile-nav-tabs, .btn, .ios-btn, #printBtn, .site-footer, .no-print, .profile-hero-card {
        display: none !important;
      }
      .tab-content-panel {
        display: none !important;
      }
      #tab-report {
        display: block !important;
      }
      .metric-card, .ios-glass {
        background: transparent !important;
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        color: #000 !important;
      }
      .text-muted, [style*="color: #94a3b8"] {
        color: #555 !important;
      }
      h1, h2, h3, h4, strong {
        color: #000 !important;
      }
    }
  </style>
</head>
<body>

  <?php include 'include/header.php'; ?>

  <div class="site-content-wrapper" style="padding: 30px 16px 85px; max-width: 1240px; margin: 0 auto;">
    
    <!-- Toast / Alerts -->
    <?php if (!empty($flashSuccess)): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 14px; background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; margin-bottom: 20px;">
        <i class="fa fa-check-circle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($flashSuccess); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="color: #fff;"><span aria-hidden="true">&times;</span></button>
      </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 14px; background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; margin-bottom: 20px;">
        <i class="fa fa-exclamation-triangle" style="margin-right: 8px;"></i> <?php echo htmlspecialchars($flashError); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close" style="color: #fff;"><span aria-hidden="true">&times;</span></button>
      </div>
    <?php endif; ?>

    <!-- Header Section: Member Identity Card -->
    <div class="profile-hero-card no-print">
      <div class="row align-items-center">
        <div class="col-lg-8 mb-3 mb-lg-0">
          <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div class="profile-avatar-box">
              <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/' . $user['profile_photo'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo">
              <?php else: ?>
                <span><?php echo strtoupper(substr($user['fname'], 0, 1) . substr($user['lname'] ?? '', 0, 1)); ?></span>
              <?php endif; ?>
            </div>
            <div style="flex: 1; min-width: 220px;">
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h1 style="font-size: 26px; font-weight: 800; color: #fff; margin: 0; letter-spacing: -0.02em;">
                  <?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>
                </h1>
                <span class="ios-badge ios-badge-primary" style="font-size: 11px; padding: 4px 10px; border-radius: 8px;">
                  <?php echo $memberCode; ?>
                </span>
                <?php if ($membership): ?>
                  <span class="ios-badge <?php echo ($membership['status'] === 'active' && $membership['days_left'] >= 0) ? 'ios-badge-success' : 'ios-badge-warning'; ?>" style="font-size: 11px;">
                    <i class="fa fa-id-card-o"></i> <?php echo htmlspecialchars($membership['plan_name'] ?? 'Membership'); ?> 
                    (<?php echo ($membership['status'] === 'active' && $membership['days_left'] >= 0) ? $membership['days_left'] . 'd left' : 'Expired'; ?>)
                  </span>
                <?php endif; ?>
              </div>
              
              <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; font-size: 13px; color: #94a3b8;">
                <span><i class="fa fa-envelope" style="color: var(--color-primary); margin-right: 5px;"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                <span><i class="fa fa-phone" style="color: var(--color-primary); margin-right: 5px;"></i> <?php echo htmlspecialchars($user['mobile']); ?></span>
                <?php if ($memberAge !== null): ?>
                  <span><i class="fa fa-calendar" style="color: var(--color-primary); margin-right: 5px;"></i> <?php echo $memberAge; ?> yrs</span>
                <?php endif; ?>
                <span><i class="fa fa-map-marker" style="color: var(--color-primary); margin-right: 5px;"></i> <?php echo htmlspecialchars($user['city'] ?: 'City Not Set'); ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4 text-lg-right">
          <div style="display: inline-block; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 14px 18px; text-align: left; min-width: 200px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Profile Completion</span>
              <span style="font-size: 13px; font-weight: 800; color: var(--color-primary);"><?php echo $profileCompletion; ?>%</span>
            </div>
            <div style="height: 6px; border-radius: 10px; background: rgba(255,255,255,0.08); overflow: hidden;">
              <div style="height: 100%; width: <?php echo $profileCompletion; ?>%; background: linear-gradient(90deg, var(--color-primary), var(--color-accent)); border-radius: 10px; transition: width 0.4s ease;"></div>
            </div>
            <div style="margin-top: 10px; display: flex; gap: 8px;">
              <button type="button" class="ios-btn ios-btn-primary" style="padding: 6px 14px; font-size: 12px; border-radius: 8px; flex: 1; text-align: center;" onclick="switchProfileTab('tab-measurements')">
                <i class="fa fa-plus-circle"></i> Log Measurements
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 6-Tab Responsive Navigation Bar -->
    <div class="profile-nav-tabs no-print" id="profileTabs">
      <a href="#overview" class="profile-tab-btn active" onclick="switchProfileTab('tab-overview', this)">
        <i class="fa fa-dashboard"></i> Overview
      </a>
      <a href="#personal" class="profile-tab-btn" onclick="switchProfileTab('tab-personal', this)">
        <i class="fa fa-user"></i> Personal Info
      </a>
      <a href="#measurements" class="profile-tab-btn" onclick="switchProfileTab('tab-measurements', this)">
        <i class="fa fa-sliders"></i> Body Measurements
      </a>
      <a href="#metrics" class="profile-tab-btn" onclick="switchProfileTab('tab-metrics', this)">
        <i class="fa fa-heartbeat"></i> Fitness Metrics
      </a>
      <a href="#history" class="profile-tab-btn" onclick="switchProfileTab('tab-history', this)">
        <i class="fa fa-line-chart"></i> Progress History
      </a>
      <a href="#report" class="profile-tab-btn" onclick="switchProfileTab('tab-report', this)">
        <i class="fa fa-file-text-o"></i> Fitness Report
      </a>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 1: OVERVIEW -->
    <!-- ======================================================================== -->
    <div id="tab-overview" class="tab-content-panel active">
      <div class="row">
        <!-- 1. Latest Weight -->
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);">
              <i class="fa fa-balance-scale"></i>
            </div>
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Current Weight</div>
            <div style="font-size: 28px; font-weight: 800; color: #fff; margin: 4px 0;">
              <?php if ($currentWeightKg): ?>
                <?php echo ($unitPref === 'imperial') ? round(fm_kg_to_lb($currentWeightKg), 1) . ' <small style="font-size: 14px; font-weight: 600; color: #94a3b8;">lb</small>' : $currentWeightKg . ' <small style="font-size: 14px; font-weight: 600; color: #94a3b8;">kg</small>'; ?>
              <?php else: ?>
                <span style="font-size: 16px; color: #64748b;">Not logged</span>
              <?php endif; ?>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
              <?php if ($totalMeasurements > 1): ?>
                <span style="color: <?php echo ($weightDeltaKg <= 0) ? '#34d399' : '#fbbf24'; ?>; font-weight: 600;">
                  <i class="fa fa-<?php echo ($weightDeltaKg <= 0) ? 'arrow-down' : 'arrow-up'; ?>"></i> 
                  <?php echo abs($weightDeltaKg); ?> kg overall
                </span>
              <?php else: ?>
                Baseline initial entry
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 2. Height -->
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3);">
              <i class="fa fa-arrows-v"></i>
            </div>
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Height</div>
            <div style="font-size: 28px; font-weight: 800; color: #fff; margin: 4px 0;">
              <?php if ($currentHeightCm): ?>
                <?php 
                if ($unitPref === 'imperial') {
                    $ftIn = fm_cm_to_ft_in($currentHeightCm);
                    echo $ftIn['feet'] . "' " . $ftIn['inches'] . '"';
                } else {
                    echo $currentHeightCm . ' <small style="font-size: 14px; font-weight: 600; color: #94a3b8;">cm</small>';
                }
                ?>
              <?php else: ?>
                <span style="font-size: 16px; color: #64748b;">Not set</span>
              <?php endif; ?>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
              Used for metabolic formulas
            </div>
          </div>
        </div>

        <!-- 3. BMI Index -->
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">
              <i class="fa fa-calculator"></i>
            </div>
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Body Mass Index</div>
            <div style="font-size: 28px; font-weight: 800; color: #fff; margin: 4px 0;">
              <?php echo $bmiData ? $bmiData['bmi'] : '<span style="font-size: 16px; color: #64748b;">--</span>'; ?>
            </div>
            <div>
              <?php if ($bmiData): ?>
                <span class="ios-badge <?php echo $bmiData['badge_class']; ?>" style="font-size: 10.5px; padding: 2px 8px;">
                  <?php echo $bmiData['category']; ?>
                </span>
              <?php else: ?>
                <span style="font-size: 12px; color: #64748b;">Enter height &amp; weight</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- 4. Body Fat % -->
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);">
              <i class="fa fa-percent"></i>
            </div>
            <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;">Body Fat %</div>
            <div style="font-size: 28px; font-weight: 800; color: #fff; margin: 4px 0;">
              <?php if ($currentBodyFat): ?>
                <?php echo $currentBodyFat; ?><small style="font-size: 14px; font-weight: 600; color: #94a3b8;">%</small>
              <?php else: ?>
                <span style="font-size: 16px; color: #64748b;">Optional</span>
              <?php endif; ?>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
              <?php echo $latestM ? 'Source: ' . ucfirst(str_replace('_', ' ', $latestM['body_fat_source'])) : 'Via device/scale'; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Overview Row 2: Metabolic Summary & Goal Guidance -->
      <div class="row">
        <!-- Goal & Caloric Recommendation -->
        <div class="col-lg-8">
          <div class="ios-glass" style="padding: 24px 28px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
              <div>
                <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0;">
                  <i class="fa fa-bullseye" style="color: var(--color-primary); margin-right: 8px;"></i> Fitness Goal &amp; Nutrition Target
                </h3>
                <p style="font-size: 13px; color: #94a3b8; margin: 2px 0 0;">
                  Derived from your activity profile and Mifflin–St Jeor expenditure models
                </p>
              </div>
              <span class="ios-badge ios-badge-primary" style="text-transform: uppercase; font-size: 11px;">
                <?php echo str_replace('_', ' ', $user['fitness_goal'] ?? 'general_fitness'); ?>
              </span>
            </div>

            <div class="row">
              <div class="col-sm-4 mb-3 mb-sm-0">
                <div style="background: rgba(255,255,255,0.04); border-radius: 14px; padding: 14px; border: 1px solid rgba(255,255,255,0.06);">
                  <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Basal Metabolic Rate</div>
                  <div style="font-size: 22px; font-weight: 800; color: #38bdf8; margin: 4px 0;">
                    <?php echo ($bmrData && $bmrData['status'] === 'calculated') ? number_format($bmrData['bmr']) . ' <small style="font-size: 12px; color: #94a3b8;">kcal</small>' : '--'; ?>
                  </div>
                  <div style="font-size: 11.5px; color: #64748b;">Resting metabolism</div>
                </div>
              </div>

              <div class="col-sm-4 mb-3 mb-sm-0">
                <div style="background: rgba(255,255,255,0.04); border-radius: 14px; padding: 14px; border: 1px solid rgba(255,255,255,0.06);">
                  <div style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Total Daily (TDEE)</div>
                  <div style="font-size: 22px; font-weight: 800; color: #fbbf24; margin: 4px 0;">
                    <?php echo ($tdeeData && $tdeeData['status'] === 'calculated') ? number_format($tdeeData['tdee']) . ' <small style="font-size: 12px; color: #94a3b8;">kcal</small>' : '--'; ?>
                  </div>
                  <div style="font-size: 11.5px; color: #64748b;">With lifestyle activity</div>
                </div>
              </div>

              <div class="col-sm-4">
                <div style="background: rgba(16, 185, 129, 0.1); border-radius: 14px; padding: 14px; border: 1px solid rgba(16, 185, 129, 0.25);">
                  <div style="font-size: 11px; text-transform: uppercase; color: #34d399; font-weight: 700;">Target Daily Calories</div>
                  <div style="font-size: 22px; font-weight: 800; color: #34d399; margin: 4px 0;">
                    <?php echo ($calorieTargetData && $calorieTargetData['status'] === 'calculated') ? number_format($calorieTargetData['target_calories']) . ' <small style="font-size: 12px; color: #34d399;">kcal</small>' : '--'; ?>
                  </div>
                  <div style="font-size: 11.5px; color: #94a3b8;">
                    <?php echo ($calorieTargetData && $calorieTargetData['status'] === 'calculated') ? $calorieTargetData['goal_label'] : 'Complete profile'; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Macro Progress Bars if calculated -->
            <?php if ($macroData && $macroData['status'] === 'calculated'): ?>
              <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08);">
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 8px;">
                  <span><strong>Protein:</strong> <?php echo $macroData['protein']['grams']; ?>g (<?php echo $macroData['protein']['pct']; ?>%)</span>
                  <span><strong>Carbs:</strong> <?php echo $macroData['carbs']['grams']; ?>g (<?php echo $macroData['carbs']['pct']; ?>%)</span>
                  <span><strong>Fat:</strong> <?php echo $macroData['fat']['grams']; ?>g (<?php echo $macroData['fat']['pct']; ?>%)</span>
                </div>
                <div style="display: flex; height: 10px; border-radius: 8px; overflow: hidden; background: rgba(255,255,255,0.08);">
                  <div style="width: <?php echo $macroData['protein']['pct']; ?>%; background: #38bdf8;" title="Protein"></div>
                  <div style="width: <?php echo $macroData['carbs']['pct']; ?>%; background: #fbbf24;" title="Carbohydrates"></div>
                  <div style="width: <?php echo $macroData['fat']['pct']; ?>%; background: #f87171;" title="Fat"></div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Proportions & Hydration Snapshot -->
        <div class="col-lg-4">
          <div class="ios-glass" style="padding: 24px 28px; margin-bottom: 20px;">
            <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 16px;">
              <i class="fa fa-tint" style="color: #38bdf8; margin-right: 8px;"></i> Hydration &amp; Proportions
            </h3>

            <!-- Hydration -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
              <div>
                <div style="font-weight: 600; font-size: 13.5px; color: #fff;">Daily Water Target</div>
                <div style="font-size: 11.5px; color: #94a3b8;">~35ml per kg bodyweight</div>
              </div>
              <div style="text-align: right;">
                <span style="font-size: 20px; font-weight: 800; color: #38bdf8;"><?php echo $hydrationData ? $hydrationData['liters'] . 'L' : '--'; ?></span>
                <div style="font-size: 11px; color: #64748b;"><?php echo $hydrationData ? $hydrationData['glasses'] . ' glasses' : ''; ?></div>
              </div>
            </div>

            <!-- Waist-to-Height Ratio -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
              <div>
                <div style="font-weight: 600; font-size: 13.5px; color: #fff;">Waist-to-Height Ratio</div>
                <div style="font-size: 11.5px; color: #94a3b8;">Benchmark: &lt; 0.50</div>
              </div>
              <div style="text-align: right;">
                <span style="font-size: 18px; font-weight: 800; color: #fff;"><?php echo $whtrData ? $whtrData['ratio'] : '--'; ?></span>
                <?php if ($whtrData): ?>
                  <div><span class="ios-badge <?php echo $whtrData['badge_class']; ?>" style="font-size: 10px;"><?php echo $whtrData['status']; ?></span></div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Waist-to-Hip Ratio -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 0;">
              <div>
                <div style="font-weight: 600; font-size: 13.5px; color: #fff;">Waist-to-Hip Ratio</div>
                <div style="font-size: 11.5px; color: #94a3b8;">WHO standard</div>
              </div>
              <div style="text-align: right;">
                <span style="font-size: 18px; font-weight: 800; color: #fff;"><?php echo $whrData ? $whrData['ratio'] : '--'; ?></span>
                <?php if ($whrData): ?>
                  <div><span class="ios-badge <?php echo $whrData['badge_class']; ?>" style="font-size: 10px;"><?php echo $whrData['status']; ?></span></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 2: PERSONAL INFORMATION FORM -->
    <!-- ======================================================================== -->
    <div id="tab-personal" class="tab-content-panel">
      <div class="ios-glass" style="padding: 32px 36px; max-width: 900px; margin: 0 auto;">
        <div style="margin-bottom: 24px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 16px;">
          <h2 style="font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 4px;">
            Personal Identity &amp; Fitness Profile
          </h2>
          <p style="font-size: 13.5px; color: #94a3b8; margin: 0;">
            Update your registered gym information, physical baseline, and preferred units.
          </p>
        </div>

        <form method="post" enctype="multipart/form-data" autocomplete="off">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="update_profile">

          <!-- 1. Name & Contact -->
          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">First Name <span style="color: #f87171;">*</span></label>
                <input type="text" name="fname" class="ios-input" value="<?php echo htmlspecialchars($user['fname']); ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Last Name</label>
                <input type="text" name="lname" class="ios-input" value="<?php echo htmlspecialchars($user['lname']); ?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Email Address (Account Identifier)</label>
                <input type="email" class="ios-input" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="opacity: 0.65; cursor: not-allowed;">
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Mobile Number</label>
                <input type="text" name="mobile" class="ios-input" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
              </div>
            </div>
          </div>

          <!-- 2. Avatar & Demographic Baseline -->
          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Profile Avatar Image</label>
                <input type="file" name="profile_photo" class="ios-input" accept="image/jpeg,image/png,image/webp" style="padding: 8px;">
                <small style="color: #64748b; font-size: 11px;">Max size 5MB. Formats: JPG, PNG, WEBP.</small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Date of Birth</label>
                <input type="date" name="dob" class="ios-input" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">
                <small style="color: #64748b; font-size: 11px;">Used to dynamically calculate your metabolic age.</small>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Biological Sex / Gender (For Metabolism Models)</label>
                <select name="gender" class="ios-input">
                  <option value="prefer_not_to_say" <?php echo ($user['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say (Optional)</option>
                  <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                  <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                  <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <small style="color: #64748b; font-size: 11px;">Strictly private. Needed for Mifflin-St Jeor formula precision.</small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Standing Height (cm)</label>
                <input type="number" step="0.1" name="height" class="ios-input" value="<?php echo htmlspecialchars($user['height'] ?? ''); ?>" placeholder="e.g. 178">
                <small style="color: #64748b; font-size: 11px;">Base standing height used across BMI and BMR.</small>
              </div>
            </div>
          </div>

          <!-- 3. Fitness Goals & Preferences -->
          <div class="row">
            <div class="col-md-4">
              <div class="ios-form-group">
                <label class="ios-label">Primary Fitness Goal</label>
                <select name="fitness_goal" class="ios-input">
                  <option value="fat_loss" <?php echo ($user['fitness_goal'] ?? '') === 'fat_loss' ? 'selected' : ''; ?>>Fat Loss (Caloric Deficit)</option>
                  <option value="muscle_gain" <?php echo ($user['fitness_goal'] ?? '') === 'muscle_gain' ? 'selected' : ''; ?>>Muscle Gain (Hypertrophy Surplus)</option>
                  <option value="strength" <?php echo ($user['fitness_goal'] ?? '') === 'strength' ? 'selected' : ''; ?>>Strength &amp; Power</option>
                  <option value="endurance" <?php echo ($user['fitness_goal'] ?? '') === 'endurance' ? 'selected' : ''; ?>>Endurance &amp; Stamina</option>
                  <option value="maintenance" <?php echo ($user['fitness_goal'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Weight Maintenance</option>
                  <option value="general_fitness" <?php echo ($user['fitness_goal'] ?? '') === 'general_fitness' ? 'selected' : ''; ?>>General Health &amp; Fitness</option>
                </select>
              </div>
            </div>

            <div class="col-md-4">
              <div class="ios-form-group">
                <label class="ios-label">Weekly Activity Level</label>
                <select name="activity_level" class="ios-input">
                  <option value="sedentary" <?php echo ($user['activity_level'] ?? '') === 'sedentary' ? 'selected' : ''; ?>>Sedentary (Little or no exercise)</option>
                  <option value="lightly_active" <?php echo ($user['activity_level'] ?? '') === 'lightly_active' ? 'selected' : ''; ?>>Light (1–3 training days/wk)</option>
                  <option value="moderately_active" <?php echo ($user['activity_level'] ?? '') === 'moderately_active' ? 'selected' : ''; ?>>Moderate (3–5 training days/wk)</option>
                  <option value="very_active" <?php echo ($user['activity_level'] ?? '') === 'very_active' ? 'selected' : ''; ?>>Very Active (6–7 training days/wk)</option>
                  <option value="extra_active" <?php echo ($user['activity_level'] ?? '') === 'extra_active' ? 'selected' : ''; ?>>Extra Active (Heavy daily labor/2x)</option>
                </select>
              </div>
            </div>

            <div class="col-md-4">
              <div class="ios-form-group">
                <label class="ios-label">Unit Preference</label>
                <select name="unit_preference" class="ios-input">
                  <option value="metric" <?php echo ($user['unit_preference'] ?? '') === 'metric' ? 'selected' : ''; ?>>Metric (kg, cm)</option>
                  <option value="imperial" <?php echo ($user['unit_preference'] ?? '') === 'imperial' ? 'selected' : ''; ?>>Imperial (lb, in)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- 4. Target & Emergency Contact -->
          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Target Goal Weight (kg)</label>
                <input type="number" step="0.1" name="target_weight" class="ios-input" value="<?php echo htmlspecialchars($user['target_weight'] ?? ''); ?>" placeholder="Optional goal weight">
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Target Date</label>
                <input type="date" name="target_date" class="ios-input" value="<?php echo htmlspecialchars($user['target_date'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="ios-input" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>" placeholder="Family or guardian">
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" class="ios-input" value="<?php echo htmlspecialchars($user['emergency_contact_phone'] ?? ''); ?>" placeholder="Phone number">
              </div>
            </div>
          </div>

          <!-- 5. Address Details -->
          <div class="row">
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">City</label>
                <input type="text" name="city" class="ios-input" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="ios-form-group">
                <label class="ios-label">State / Region</label>
                <input type="text" name="state" class="ios-input" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <div class="ios-form-group">
            <label class="ios-label">Residential Address</label>
            <input type="text" name="address" class="ios-input" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 10px 24px;">
              <i class="fa fa-save"></i> Save Profile Details
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 3: BODY MEASUREMENTS ENTRY & LEDGER -->
    <!-- ======================================================================== -->
    <div id="tab-measurements" class="tab-content-panel">
      <div class="row">
        <!-- Entry Form Card -->
        <div class="col-lg-5 mb-4">
          <div class="ios-glass" style="padding: 26px 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0;">
                <i class="fa fa-pencil" style="color: var(--color-primary); margin-right: 6px;"></i> Log Measurement
              </h3>
              <!-- Unit Selector -->
              <div class="btn-group btn-group-toggle" data-toggle="buttons">
                <label class="btn btn-sm btn-outline-light <?php echo ($unitPref === 'metric') ? 'active' : ''; ?>" id="unitMetricBtn" onclick="toggleFormUnits('metric')">
                  <input type="radio" name="unit_toggle" checked> Metric (kg/cm)
                </label>
                <label class="btn btn-sm btn-outline-light <?php echo ($unitPref === 'imperial') ? 'active' : ''; ?>" id="unitImperialBtn" onclick="toggleFormUnits('imperial')">
                  <input type="radio" name="unit_toggle"> Imperial (lb/in)
                </label>
              </div>
            </div>

            <form method="post" id="measurementForm" autocomplete="off">
              <?php echo csrf_input(); ?>
              <input type="hidden" name="action" value="add_measurement">
              <input type="hidden" name="input_unit" id="formInputUnit" value="<?php echo $unitPref; ?>">

              <div class="ios-form-group">
                <label class="ios-label">Measurement Date <span style="color: #f87171;">*</span></label>
                <input type="date" name="measurement_date" class="ios-input" value="<?php echo date('Y-m-d'); ?>" required>
              </div>

              <!-- Required Core -->
              <div class="row">
                <div class="col-6">
                  <div class="ios-form-group">
                    <label class="ios-label" id="labelWeight">Weight (<?php echo ($unitPref === 'imperial') ? 'lb' : 'kg'; ?>) <span style="color: #f87171;">*</span></label>
                    <input type="number" step="0.1" name="weight" id="inputWeight" class="ios-input" required placeholder="<?php echo ($unitPref === 'imperial') ? '165.0' : '75.0'; ?>">
                  </div>
                </div>
                <div class="col-6">
                  <div class="ios-form-group">
                    <label class="ios-label" id="labelWaist">Waist (<?php echo ($unitPref === 'imperial') ? 'in' : 'cm'; ?>) <span style="color: #f87171;">*</span></label>
                    <input type="number" step="0.1" name="waist" id="inputWaist" class="ios-input" required placeholder="<?php echo ($unitPref === 'imperial') ? '32.0' : '81.0'; ?>">
                  </div>
                </div>
              </div>

              <div class="ios-form-group">
                <label class="ios-label" id="labelHeight">Standing Height (<?php echo ($unitPref === 'imperial') ? 'inches' : 'cm'; ?>)</label>
                <input type="number" step="0.1" name="height_input" id="inputHeight" class="ios-input" value="<?php echo $currentHeightCm ? (($unitPref === 'imperial') ? round(fm_cm_to_in($currentHeightCm), 1) : $currentHeightCm) : ''; ?>" placeholder="<?php echo ($unitPref === 'imperial') ? '70.0' : '178.0'; ?>">
              </div>

              <!-- Accordion / Collapsible Optional Measurements -->
              <div style="margin: 16px 0;">
                <a href="#optionalMeasurementsCollapse" data-toggle="collapse" class="btn btn-sm btn-outline-light w-100" style="border-radius: 10px; font-size: 12.5px;">
                  <i class="fa fa-sliders"></i> Optional Circumferences &amp; Body Fat <i class="fa fa-angle-down"></i>
                </a>
              </div>

              <div class="collapse" id="optionalMeasurementsCollapse">
                <div style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 14px; margin-bottom: 16px; border: 1px solid rgba(255,255,255,0.06);">
                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin-bottom: 8px;">Upper Body</div>
                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Neck</label>
                        <input type="number" step="0.1" name="neck" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Chest</label>
                        <input type="number" step="0.1" name="chest" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Shoulders</label>
                        <input type="number" step="0.1" name="shoulders" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Hip Circumference</label>
                        <input type="number" step="0.1" name="hip" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Left Arm</label>
                        <input type="number" step="0.1" name="arm_left" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Right Arm</label>
                        <input type="number" step="0.1" name="arm_right" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                  </div>

                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin: 10px 0 8px;">Lower Body</div>
                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Left Thigh</label>
                        <input type="number" step="0.1" name="thigh_left" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Right Thigh</label>
                        <input type="number" step="0.1" name="thigh_right" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                  </div>

                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Left Calf</label>
                        <input type="number" step="0.1" name="calf_left" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Right Calf</label>
                        <input type="number" step="0.1" name="calf_right" class="ios-input" style="padding: 8px 12px; font-size: 13px;">
                      </div>
                    </div>
                  </div>

                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin: 10px 0 8px;">Body Composition</div>
                  <div class="row">
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Body Fat %</label>
                        <input type="number" step="0.1" name="body_fat_pct" class="ios-input" style="padding: 8px 12px; font-size: 13px;" placeholder="e.g. 16.5">
                      </div>
                    </div>
                    <div class="col-6">
                      <div class="ios-form-group">
                        <label class="ios-label" style="font-size: 11px;">Source</label>
                        <select name="body_fat_source" class="ios-input" style="padding: 8px 12px; font-size: 12px;">
                          <option value="manual">Manual Entry</option>
                          <option value="scale_device">Smart Scale</option>
                          <option value="caliper">Skinfold Caliper</option>
                          <option value="dexa">DEXA Scan</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="ios-form-group">
                <label class="ios-label">Workout / Session Notes</label>
                <textarea name="notes" class="ios-input" rows="2" placeholder="e.g. Morning fasted weigh-in after leg day"></textarea>
              </div>

              <button type="submit" class="ios-btn ios-btn-primary w-100" style="padding: 10px; border-radius: 12px; font-weight: 600;">
                <i class="fa fa-save"></i> Save Measurement
              </button>
            </form>
          </div>
        </div>

        <!-- History Ledger Table -->
        <div class="col-lg-7">
          <div class="ios-glass" style="padding: 26px 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0;">
                <i class="fa fa-history" style="color: var(--color-primary); margin-right: 6px;"></i> Recorded History (<?php echo $totalMeasurements; ?>)
              </h3>
            </div>

            <?php if (!empty($measurements)): ?>
              <div class="table-responsive">
                <table class="table table-hover" style="margin: 0; font-size: 13px;">
                  <thead>
                    <tr>
                      <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Date</th>
                      <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Weight</th>
                      <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Waist</th>
                      <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Body Fat</th>
                      <th style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; text-align: right;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($measurements as $idx => $m): ?>
                      <tr>
                        <td>
                          <strong><?php echo date('d M Y', strtotime($m['measurement_date'])); ?></strong>
                          <?php if (!empty($m['notes'])): ?>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($m['notes']); ?></div>
                          <?php endif; ?>
                        </td>
                        <td style="color: #38bdf8; font-weight: 700;">
                          <?php echo ($unitPref === 'imperial') ? round(fm_kg_to_lb($m['weight_kg']), 1) . ' lb' : $m['weight_kg'] . ' kg'; ?>
                        </td>
                        <td>
                          <?php echo ($unitPref === 'imperial') ? round(fm_cm_to_in($m['waist_cm']), 1) . ' in' : $m['waist_cm'] . ' cm'; ?>
                        </td>
                        <td>
                          <?php echo !empty($m['body_fat_pct']) ? $m['body_fat_pct'] . '%' : '<span style="color: #64748b;">--</span>'; ?>
                        </td>
                        <td style="text-align: right;">
                          <form method="post" style="display: inline;" onsubmit="return confirm('Delete this measurement entry from your permanent history?');">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="action" value="delete_measurement">
                            <input type="hidden" name="measurement_id" value="<?php echo $m['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 2px 8px; font-size: 11px; border-radius: 6px;">
                              <i class="fa fa-trash"></i>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                <i class="fa fa-balance-scale" style="font-size: 44px; opacity: 0.35; margin-bottom: 12px;"></i>
                <h4 style="font-size: 16px; font-weight: 700; color: #fff;">No Measurements Logged Yet</h4>
                <p style="font-size: 13px; max-width: 380px; margin: 0 auto;">
                  Record your first weight and waist circumference using the form on the left to activate progress tracking and metabolic projections.
                </p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 4: FITNESS METRICS & CALCULATIONS -->
    <!-- ======================================================================== -->
    <div id="tab-metrics" class="tab-content-panel">
      <div class="row">
        <!-- 1. BMI Deep Dive -->
        <div class="col-md-6 col-lg-4">
          <div class="metric-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="ios-badge ios-badge-primary">Screening Tool</span>
              <span style="font-size: 12px; color: #94a3b8;"><?php echo $latestM ? date('d M Y', strtotime($latestM['measurement_date'])) : ''; ?></span>
            </div>
            <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 4px;">Body Mass Index (BMI)</h3>
            <div style="font-size: 34px; font-weight: 800; color: #38bdf8; margin: 8px 0;">
              <?php echo $bmiData ? $bmiData['bmi'] : '--'; ?>
            </div>
            <?php if ($bmiData): ?>
              <div style="margin-bottom: 12px;">
                <span class="ios-badge <?php echo $bmiData['badge_class']; ?>" style="font-size: 12px; padding: 4px 10px;">
                  <?php echo $bmiData['category']; ?>
                </span>
              </div>
              <p style="font-size: 12px; color: #94a3b8; line-height: 1.4;">
                <?php echo $bmiData['message'] ?? $bmiData['explanation']; ?>
              </p>
              <?php if (!empty($bmiData['healthy_range'])): ?>
                <div style="font-size: 12px; background: rgba(255,255,255,0.04); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.06);">
                  Normal WHO Range for your height: <strong><?php echo $bmiData['healthy_range']['min_kg']; ?> – <?php echo $bmiData['healthy_range']['max_kg']; ?> kg</strong>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <p style="font-size: 12.5px; color: #64748b;">Requires recorded height and weight.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- 2. BMR (Mifflin–St Jeor) -->
        <div class="col-md-6 col-lg-4">
          <div class="metric-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="ios-badge ios-badge-info">Resting Energy</span>
              <span style="font-size: 12px; color: #94a3b8;">Mifflin–St Jeor</span>
            </div>
            <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 4px;">Basal Metabolic Rate (BMR)</h3>
            <div style="font-size: 34px; font-weight: 800; color: #818cf8; margin: 8px 0;">
              <?php echo ($bmrData && $bmrData['status'] === 'calculated') ? number_format($bmrData['bmr']) . ' <small style="font-size: 14px; color: #94a3b8;">kcal/day</small>' : '--'; ?>
            </div>
            <?php if ($bmrData && $bmrData['status'] === 'calculated'): ?>
              <div style="font-size: 12px; color: #94a3b8; line-height: 1.4; margin-bottom: 10px;">
                <?php echo $bmrData['message']; ?>
              </div>
              <div style="font-size: 11.5px; color: #64748b; font-family: monospace;">
                Formula: <?php echo $bmrData['formula']; ?>
              </div>
            <?php elseif ($bmrData && $bmrData['status'] === 'unspecified_sex'): ?>
              <div class="alert alert-warning" style="font-size: 11.5px; padding: 8px 10px; border-radius: 8px; background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); color: #fbbf24;">
                <?php echo $bmrData['message']; ?>
              </div>
            <?php else: ?>
              <p style="font-size: 12.5px; color: #64748b;">Requires height, weight, and DOB in profile.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- 3. TDEE Expenditure -->
        <div class="col-md-6 col-lg-4">
          <div class="metric-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="ios-badge ios-badge-warning">Lifestyle Active</span>
              <span style="font-size: 12px; color: #94a3b8;">Activity x Multiplier</span>
            </div>
            <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 4px;">Daily Energy (TDEE)</h3>
            <div style="font-size: 34px; font-weight: 800; color: #fbbf24; margin: 8px 0;">
              <?php echo ($tdeeData && $tdeeData['status'] === 'calculated') ? number_format($tdeeData['tdee']) . ' <small style="font-size: 14px; color: #94a3b8;">kcal/day</small>' : '--'; ?>
            </div>
            <?php if ($tdeeData && $tdeeData['status'] === 'calculated'): ?>
              <div style="font-size: 12px; color: #94a3b8; line-height: 1.4; margin-bottom: 8px;">
                Activity: <strong><?php echo $tdeeData['activity_label']; ?> (x<?php echo $tdeeData['multiplier']; ?>)</strong>
              </div>
              <p style="font-size: 11.5px; color: #64748b; margin: 0;">
                <?php echo $tdeeData['explanation']; ?>
              </p>
            <?php else: ?>
              <p style="font-size: 12.5px; color: #64748b;">Requires BMR calculation.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- 4. Goal-Based Calorie Target -->
        <div class="col-md-6 col-lg-6">
          <div class="metric-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <span class="ios-badge ios-badge-success">Goal Calibrated</span>
              <span style="font-size: 12px; color: #34d399; font-weight: 600;"><?php echo ($calorieTargetData && $calorieTargetData['status'] === 'calculated') ? $calorieTargetData['goal_label'] : ''; ?></span>
            </div>
            <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 4px;">Recommended Calorie Target</h3>
            <div style="font-size: 36px; font-weight: 800; color: #34d399; margin: 8px 0;">
              <?php echo ($calorieTargetData && $calorieTargetData['status'] === 'calculated') ? number_format($calorieTargetData['target_calories']) . ' <small style="font-size: 14px; color: #34d399;">kcal</small>' : '--'; ?>
            </div>
            <?php if ($calorieTargetData && $calorieTargetData['status'] === 'calculated'): ?>
              <p style="font-size: 12.5px; color: #94a3b8; margin-bottom: 8px;">
                <?php if ($calorieTargetData['delta'] < 0): ?>
                  Includes a safe, conservative deficit of <?php echo abs($calorieTargetData['delta']); ?> kcal for sustainable fat loss.
                <?php elseif ($calorieTargetData['delta'] > 0): ?>
                  Includes a controlled surplus of +<?php echo $calorieTargetData['delta']; ?> kcal to facilitate hypertrophy and recovery.
                <?php else: ?>
                  Calibrated to maintain current weight at balanced energy expenditure.
                <?php endif; ?>
              </p>
              <?php if ($calorieTargetData['safety_floor_applied']): ?>
                <div class="alert alert-warning" style="font-size: 11.5px; padding: 6px 10px; border-radius: 8px;">
                  Safety floor applied (≥ <?php echo $calorieTargetData['min_floor']; ?> kcal/day) to prevent metabolic suppression.
                </div>
              <?php endif; ?>
            <?php elseif ($calorieTargetData && $calorieTargetData['status'] === 'minor_limitation'): ?>
              <p style="font-size: 12px; color: #fbbf24;"><?php echo $calorieTargetData['message']; ?></p>
            <?php else: ?>
              <p style="font-size: 12.5px; color: #64748b;">Complete profile DOB, height, and measurements to calculate.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- 5. Macronutrient Distribution -->
        <div class="col-md-6 col-lg-6">
          <div class="metric-card">
            <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 12px;">Macronutrient Distribution</h3>
            <?php if ($macroData && $macroData['status'] === 'calculated'): ?>
              <div class="row text-center mb-3">
                <div class="col-4">
                  <div style="background: rgba(56, 189, 248, 0.1); border-radius: 12px; padding: 10px; border: 1px solid rgba(56, 189, 248, 0.25);">
                    <div style="font-size: 11px; text-transform: uppercase; color: #38bdf8; font-weight: 700;">Protein</div>
                    <div style="font-size: 22px; font-weight: 800; color: #fff; margin: 2px 0;"><?php echo $macroData['protein']['grams']; ?>g</div>
                    <div style="font-size: 11px; color: #94a3b8;"><?php echo $macroData['protein']['pct']; ?>% (~<?php echo $macroData['protein']['calories']; ?> kcal)</div>
                  </div>
                </div>
                <div class="col-4">
                  <div style="background: rgba(251, 191, 36, 0.1); border-radius: 12px; padding: 10px; border: 1px solid rgba(251, 191, 36, 0.25);">
                    <div style="font-size: 11px; text-transform: uppercase; color: #fbbf24; font-weight: 700;">Carbs</div>
                    <div style="font-size: 22px; font-weight: 800; color: #fff; margin: 2px 0;"><?php echo $macroData['carbs']['grams']; ?>g</div>
                    <div style="font-size: 11px; color: #94a3b8;"><?php echo $macroData['carbs']['pct']; ?>% (~<?php echo $macroData['carbs']['calories']; ?> kcal)</div>
                  </div>
                </div>
                <div class="col-4">
                  <div style="background: rgba(248, 113, 113, 0.1); border-radius: 12px; padding: 10px; border: 1px solid rgba(248, 113, 113, 0.25);">
                    <div style="font-size: 11px; text-transform: uppercase; color: #f87171; font-weight: 700;">Fat</div>
                    <div style="font-size: 22px; font-weight: 800; color: #fff; margin: 2px 0;"><?php echo $macroData['fat']['grams']; ?>g</div>
                    <div style="font-size: 11px; color: #94a3b8;"><?php echo $macroData['fat']['pct']; ?>% (~<?php echo $macroData['fat']['calories']; ?> kcal)</div>
                  </div>
                </div>
              </div>
              <div style="display: flex; height: 8px; border-radius: 6px; overflow: hidden;">
                <div style="width: <?php echo $macroData['protein']['pct']; ?>%; background: #38bdf8;"></div>
                <div style="width: <?php echo $macroData['carbs']['pct']; ?>%; background: #fbbf24;"></div>
                <div style="width: <?php echo $macroData['fat']['pct']; ?>%; background: #f87171;"></div>
              </div>
            <?php else: ?>
              <p style="font-size: 12.5px; color: #64748b;">Macro distribution will render once a caloric target is active.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 5: PROGRESS HISTORY & CHARTS -->
    <!-- ======================================================================== -->
    <div id="tab-history" class="tab-content-panel">
      <?php if (!empty($measurements)): ?>
        <div class="row">
          <!-- Weight Trend Chart -->
          <div class="col-lg-8 mb-4">
            <div class="ios-glass" style="padding: 24px 28px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <div>
                  <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0;">
                    <i class="fa fa-line-chart" style="color: var(--color-primary); margin-right: 6px;"></i> Weight Trend Over Time
                  </h3>
                  <p style="font-size: 12.5px; color: #94a3b8; margin: 2px 0 0;">Chronological weigh-ins logged in your personal profile</p>
                </div>
                <span class="ios-badge ios-badge-success" style="font-size: 11px;">
                  <?php echo count($measurements); ?> Recorded Points
                </span>
              </div>
              <div style="height: 280px; position: relative;">
                <canvas id="weightProgressChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Waist Circumference Chart -->
          <div class="col-lg-4 mb-4">
            <div class="ios-glass" style="padding: 24px 28px;">
              <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 4px;">
                <i class="fa fa-dot-circle-o" style="color: #fbbf24; margin-right: 6px;"></i> Waist Circumference
              </h3>
              <p style="font-size: 12.5px; color: #94a3b8; margin: 0 0 16px;">Core waistline tracking</p>
              <div style="height: 280px; position: relative;">
                <canvas id="waistProgressChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="ios-glass text-center" style="padding: 60px 20px;">
          <i class="fa fa-line-chart" style="font-size: 54px; opacity: 0.3; margin-bottom: 16px; color: var(--color-primary);"></i>
          <h3 style="font-size: 20px; font-weight: 700; color: #fff;">No Progress History Available</h3>
          <p style="color: #94a3b8; max-width: 440px; margin: 6px auto 20px;">
            Once you log at least two body measurement records over time, interactive trend charts will automatically appear here.
          </p>
          <button type="button" class="ios-btn ios-btn-primary" onclick="switchProfileTab('tab-measurements')">
            <i class="fa fa-plus-circle"></i> Log Measurement Entry
          </button>
        </div>
      <?php endif; ?>
    </div>

    <!-- ======================================================================== -->
    <!-- TAB 6: FITNESS & NUTRITION REPORT -->
    <!-- ======================================================================== -->
    <div id="tab-report" class="tab-content-panel">
      <div class="ios-glass" style="padding: 36px 40px; max-width: 960px; margin: 0 auto; position: relative;">
        
        <!-- Print Trigger Bar -->
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.08);">
          <div>
            <h2 style="font-size: 22px; font-weight: 800; color: #fff; margin: 0;">Comprehensive Member Health Report</h2>
            <p style="font-size: 13px; color: #94a3b8; margin: 2px 0 0;">Printable physical metrics, macronutrient breakdown, and proportion indicators</p>
          </div>
          <button type="button" class="ios-btn ios-btn-primary" id="printBtn" onclick="window.print()">
            <i class="fa fa-print"></i> Print / Save as PDF
          </button>
        </div>

        <!-- Report Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 20px;">
          <div>
            <div style="font-size: 24px; font-weight: 800; letter-spacing: -0.02em; color: #fff;"><?php echo htmlspecialchars($gymName); ?></div>
            <div style="font-size: 11px; text-transform: uppercase; color: var(--color-primary); font-weight: 700; letter-spacing: 0.05em;"><?php echo htmlspecialchars($gymTagline); ?> — MEMBER METRIC REPORT</div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 13px; color: #94a3b8;">Report Date: <strong><?php echo date('d M Y'); ?></strong></div>
            <div style="font-size: 13px; color: #94a3b8;">Member Code: <strong><?php echo $memberCode; ?></strong></div>
          </div>
        </div>

        <!-- Bio Summary -->
        <div style="background: rgba(255,255,255,0.03); border-radius: 14px; padding: 18px 22px; margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.06);">
          <div class="row">
            <div class="col-sm-3 mb-2 mb-sm-0">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Member Name</span>
              <strong style="color: #fff; font-size: 15px;"><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></strong>
            </div>
            <div class="col-sm-3 mb-2 mb-sm-0">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Age &amp; Sex</span>
              <strong style="color: #fff; font-size: 15px;"><?php echo ($memberAge !== null) ? $memberAge . ' yrs' : '--'; ?> / <?php echo ucfirst($user['gender'] ?? 'Unspecified'); ?></strong>
            </div>
            <div class="col-sm-3 mb-2 mb-sm-0">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Primary Goal</span>
              <strong style="color: var(--color-primary); font-size: 15px;"><?php echo ucfirst(str_replace('_', ' ', $user['fitness_goal'] ?? 'General')); ?></strong>
            </div>
            <div class="col-sm-3">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Active Membership</span>
              <strong style="color: #34d399; font-size: 15px;"><?php echo htmlspecialchars($membership['plan_name'] ?? 'Active'); ?></strong>
            </div>
          </div>
        </div>

        <!-- Metrics Grid in Report -->
        <div class="row mb-4">
          <div class="col-3">
            <div style="border-left: 3px solid #38bdf8; padding-left: 12px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Latest Weight</span>
              <span style="font-size: 22px; font-weight: 800; color: #fff;"><?php echo $currentWeightKg ? $currentWeightKg . ' kg' : '--'; ?></span>
            </div>
          </div>
          <div class="col-3">
            <div style="border-left: 3px solid #818cf8; padding-left: 12px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Standing Height</span>
              <span style="font-size: 22px; font-weight: 800; color: #fff;"><?php echo $currentHeightCm ? $currentHeightCm . ' cm' : '--'; ?></span>
            </div>
          </div>
          <div class="col-3">
            <div style="border-left: 3px solid #34d399; padding-left: 12px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Body Mass Index</span>
              <span style="font-size: 22px; font-weight: 800; color: #fff;"><?php echo $bmiData ? $bmiData['bmi'] : '--'; ?></span>
              <div style="font-size: 10px; color: #94a3b8;"><?php echo $bmiData ? $bmiData['category'] : ''; ?></div>
            </div>
          </div>
          <div class="col-3">
            <div style="border-left: 3px solid #fbbf24; padding-left: 12px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; display: block;">Calorie Target</span>
              <span style="font-size: 22px; font-weight: 800; color: #fbbf24;"><?php echo $calorieTargetData ? number_format($calorieTargetData['target_calories']) : '--'; ?></span>
              <div style="font-size: 10px; color: #94a3b8;">kcal / day</div>
            </div>
          </div>
        </div>

        <!-- Nutrition & Hydration Guidance -->
        <?php if ($macroData && $macroData['status'] === 'calculated'): ?>
          <div style="margin-bottom: 28px;">
            <h4 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.04em;">
              Macronutrient Target Estimates
            </h4>
            <div class="table-responsive">
              <table class="table table-bordered" style="color: #fff; font-size: 13px;">
                <thead>
                  <tr style="background: rgba(255,255,255,0.05); color: #94a3b8;">
                    <th>Macronutrient</th>
                    <th>Daily Target (Grams)</th>
                    <th>Caloric Equivalent</th>
                    <th>Percentage of Energy</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>Protein (4 kcal/g)</strong></td>
                    <td style="color: #38bdf8; font-weight: 700;"><?php echo $macroData['protein']['grams']; ?>g</td>
                    <td><?php echo $macroData['protein']['calories']; ?> kcal</td>
                    <td><?php echo $macroData['protein']['pct']; ?>%</td>
                  </tr>
                  <tr>
                    <td><strong>Carbohydrates (4 kcal/g)</strong></td>
                    <td style="color: #fbbf24; font-weight: 700;"><?php echo $macroData['carbs']['grams']; ?>g</td>
                    <td><?php echo $macroData['carbs']['calories']; ?> kcal</td>
                    <td><?php echo $macroData['carbs']['pct']; ?>%</td>
                  </tr>
                  <tr>
                    <td><strong>Dietary Fat (9 kcal/g)</strong></td>
                    <td style="color: #f87171; font-weight: 700;"><?php echo $macroData['fat']['grams']; ?>g</td>
                    <td><?php echo $macroData['fat']['calories']; ?> kcal</td>
                    <td><?php echo $macroData['fat']['pct']; ?>%</td>
                  </tr>
                  <tr style="background: rgba(255,255,255,0.03); font-weight: 700;">
                    <td>Total Daily Intake</td>
                    <td>--</td>
                    <td style="color: #34d399;"><?php echo number_format($macroData['total_macro_calories']); ?> kcal</td>
                    <td>100%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Clinical Disclaimer -->
        <div style="background: rgba(255,255,255,0.02); border-radius: 10px; padding: 14px 18px; border: 1px solid rgba(255,255,255,0.06); font-size: 11.5px; color: #94a3b8; line-height: 1.5;">
          <strong>Clinical &amp; Screening Notice:</strong> All mathematical estimates (BMI, BMR, TDEE, Caloric Targets, and Macronutrient Allocations) are computational models intended for sports training and general wellness screening. They do not constitute diagnostic medical advice, clinical dietetics, or prescribed disease treatment. Consult a licensed physician or certified sports dietitian before undertaking intensive caloric restriction or significant training changes.
        </div>
      </div>
    </div>

  </div>

  <script src="js/vendor/jquery-3.2.1.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Tab Switching Functionality with URL Hash persistence
    function switchProfileTab(tabId, el) {
      document.querySelectorAll('.tab-content-panel').forEach(function(p) {
        p.classList.remove('active');
      });
      document.querySelectorAll('.profile-tab-btn').forEach(function(b) {
        b.classList.remove('active');
      });

      const target = document.getElementById(tabId);
      if (target) {
        target.classList.add('active');
      }
      if (el) {
        el.classList.add('active');
      } else {
        const matchingBtn = document.querySelector('.profile-tab-btn[href="#' + tabId.replace('tab-', '') + '"]');
        if (matchingBtn) matchingBtn.classList.add('active');
      }
      window.location.hash = tabId.replace('tab-', '');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Auto-select tab based on URL hash
    document.addEventListener('DOMContentLoaded', function() {
      const hash = window.location.hash.replace('#', '');
      if (hash && document.getElementById('tab-' + hash)) {
        switchProfileTab('tab-' + hash);
      }
    });

    // Metric <-> Imperial Unit Auto-Conversion on Form
    function toggleFormUnits(unit) {
      document.getElementById('formInputUnit').value = unit;
      const weightInput = document.getElementById('inputWeight');
      const waistInput = document.getElementById('inputWaist');
      const heightInput = document.getElementById('inputHeight');

      const labelWeight = document.getElementById('labelWeight');
      const labelWaist = document.getElementById('labelWaist');
      const labelHeight = document.getElementById('labelHeight');

      if (unit === 'imperial') {
        labelWeight.innerHTML = 'Weight (lb) <span style="color: #f87171;">*</span>';
        labelWaist.innerHTML = 'Waist (in) <span style="color: #f87171;">*</span>';
        labelHeight.innerHTML = 'Standing Height (in)';

        if (weightInput.value && !isNaN(weightInput.value)) {
          weightInput.value = (parseFloat(weightInput.value) * 2.20462).toFixed(1);
        }
        if (waistInput.value && !isNaN(waistInput.value)) {
          waistInput.value = (parseFloat(waistInput.value) * 0.3937).toFixed(1);
        }
        if (heightInput.value && !isNaN(heightInput.value)) {
          heightInput.value = (parseFloat(heightInput.value) * 0.3937).toFixed(1);
        }
      } else {
        labelWeight.innerHTML = 'Weight (kg) <span style="color: #f87171;">*</span>';
        labelWaist.innerHTML = 'Waist (cm) <span style="color: #f87171;">*</span>';
        labelHeight.innerHTML = 'Standing Height (cm)';

        if (weightInput.value && !isNaN(weightInput.value)) {
          weightInput.value = (parseFloat(weightInput.value) / 2.20462).toFixed(1);
        }
        if (waistInput.value && !isNaN(waistInput.value)) {
          waistInput.value = (parseFloat(waistInput.value) / 0.3937).toFixed(1);
        }
        if (heightInput.value && !isNaN(heightInput.value)) {
          heightInput.value = (parseFloat(heightInput.value) / 0.3937).toFixed(1);
        }
      }
    }

    // Chart.js Progress History Charts
    <?php if (!empty($measurements)): ?>
      // 1. Weight Progress Chart
      const weightCanvas = document.getElementById('weightProgressChart');
      if (weightCanvas) {
        const wCtx = weightCanvas.getContext('2d');
        const wGrad = wCtx.createLinearGradient(0, 0, 0, 260);
        wGrad.addColorStop(0, 'rgba(56, 189, 248, 0.4)');
        wGrad.addColorStop(1, 'rgba(56, 189, 248, 0.01)');

        new Chart(wCtx, {
          type: 'line',
          data: {
            labels: <?php echo $chartLabels; ?>,
            datasets: [{
              label: 'Weight (<?php echo ($unitPref === 'imperial') ? 'lb' : 'kg'; ?>)',
              data: <?php echo $chartWeights; ?>,
              borderColor: '#38bdf8',
              borderWidth: 3,
              backgroundColor: wGrad,
              fill: true,
              tension: 0.3,
              pointBackgroundColor: '#ffffff',
              pointBorderColor: '#38bdf8',
              pointRadius: 4,
              pointHoverRadius: 6
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                titleColor: '#fff',
                bodyColor: '#38bdf8',
                borderColor: 'rgba(255, 255, 255, 0.12)',
                borderWidth: 1,
                cornerRadius: 10,
                padding: 12
              }
            },
            scales: {
              x: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
              y: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 11 } } }
            }
          }
        });
      }

      // 2. Waist Progress Chart
      const waistCanvas = document.getElementById('waistProgressChart');
      if (waistCanvas) {
        const waistCtx = waistCanvas.getContext('2d');
        new Chart(waistCtx, {
          type: 'line',
          data: {
            labels: <?php echo $chartLabels; ?>,
            datasets: [{
              label: 'Waist (<?php echo ($unitPref === 'imperial') ? 'in' : 'cm'; ?>)',
              data: <?php echo $chartWaists; ?>,
              borderColor: '#fbbf24',
              borderWidth: 2.5,
              backgroundColor: 'rgba(251, 191, 36, 0.1)',
              fill: true,
              tension: 0.3,
              pointBackgroundColor: '#ffffff',
              pointBorderColor: '#fbbf24',
              pointRadius: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
              y: { grid: { color: 'rgba(255, 255, 255, 0.04)' }, ticks: { color: '#94a3b8', font: { size: 11 } } }
            }
          }
        });
      }
    <?php endif; ?>
  </script>
</body>
</html>
