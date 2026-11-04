<?php
require_once 'include/config.php';
require_once 'include/fitness_metrics.php';
require_once 'include/exercise_catalog.php';
require_once 'include/workout_presets.php';

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

        // ----------------------------------------------------------------------
        // Action D: Add Exercise to Weekly Schedule
        // ----------------------------------------------------------------------
        elseif ($action === 'add_workout') {
            $dayOfWeek = in_array($_POST['day_of_week'] ?? '', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) ? $_POST['day_of_week'] : 'Monday';
            $exerciseName = trim($_POST['exercise_name'] ?? '');
            $targetMuscle = trim($_POST['target_muscle'] ?? 'Full Body');
            $sets = max(1, min(20, intval($_POST['sets'] ?? 3)));
            $reps = trim($_POST['reps'] ?? '10-12 reps');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($exerciseName)) {
                $flashError = 'Exercise name is required.';
            } else {
                $stmtAddW = $dbh->prepare("INSERT INTO tbl_workout_plans 
                    (user_id, day_of_week, target_muscle, exercise_name, sets, reps, notes, gym_id)
                    VALUES 
                    (:uid, :day, :muscle, :name, :sets, :reps, :notes, :gym_id)");
                $stmtAddW->execute([
                    ':uid' => $userId,
                    ':day' => $dayOfWeek,
                    ':muscle' => $targetMuscle,
                    ':name' => $exerciseName,
                    ':sets' => $sets,
                    ':reps' => $reps,
                    ':notes' => $notes,
                    ':gym_id' => $gymId
                ]);
                $flashSuccess = "Added '{$exerciseName}' to {$dayOfWeek} routine successfully!";
            }
        }

        // ----------------------------------------------------------------------
        // Action E: Edit Existing Exercise in Routine
        // ----------------------------------------------------------------------
        elseif ($action === 'edit_workout') {
            $wId = intval($_POST['workout_id'] ?? 0);
            $dayOfWeek = in_array($_POST['day_of_week'] ?? '', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']) ? $_POST['day_of_week'] : 'Monday';
            $exerciseName = trim($_POST['exercise_name'] ?? '');
            $targetMuscle = trim($_POST['target_muscle'] ?? 'Full Body');
            $sets = max(1, min(20, intval($_POST['sets'] ?? 3)));
            $reps = trim($_POST['reps'] ?? '10-12 reps');
            $notes = trim($_POST['notes'] ?? '');

            if ($wId <= 0 || empty($exerciseName)) {
                $flashError = 'Valid exercise ID and name are required.';
            } else {
                $stmtEditW = $dbh->prepare("UPDATE tbl_workout_plans SET 
                    day_of_week = :day,
                    target_muscle = :muscle,
                    exercise_name = :name,
                    sets = :sets,
                    reps = :reps,
                    notes = :notes
                    WHERE id = :id AND user_id = :uid");
                $stmtEditW->execute([
                    ':day' => $dayOfWeek,
                    ':muscle' => $targetMuscle,
                    ':name' => $exerciseName,
                    ':sets' => $sets,
                    ':reps' => $reps,
                    ':notes' => $notes,
                    ':id' => $wId,
                    ':uid' => $userId
                ]);
                $flashSuccess = "Updated '{$exerciseName}' successfully!";
            }
        }

        // ----------------------------------------------------------------------
        // Action F: Delete Exercise from Routine
        // ----------------------------------------------------------------------
        elseif ($action === 'delete_workout') {
            $wId = intval($_POST['workout_id'] ?? 0);
            if ($wId > 0) {
                $delWStmt = $dbh->prepare("DELETE FROM tbl_workout_plans WHERE id = :id AND user_id = :uid");
                $delWStmt->execute([':id' => $wId, ':uid' => $userId]);
                $flashSuccess = 'Exercise removed from your weekly routine.';
            }
        }

        // ----------------------------------------------------------------------
        // Action G: Apply Split Template or Clear Routine
        // ----------------------------------------------------------------------
        elseif ($action === 'apply_workout_template') {
            $template = trim($_POST['template_type'] ?? '');
            
            // Delete existing routine for this user
            $dbh->prepare("DELETE FROM tbl_workout_plans WHERE user_id = :uid")->execute([':uid' => $userId]);

            if ($template === 'clear') {
                $flashSuccess = 'Weekly routine cleared successfully.';
            } else {
                $preset = find_preset_by_identifier($template, $userId, $dbh);
                if ($preset && !empty($preset['exercises'])) {
                    $insTplStmt = $dbh->prepare("INSERT INTO tbl_workout_plans 
                        (user_id, day_of_week, target_muscle, exercise_name, sets, reps, notes, gym_id)
                        VALUES 
                        (:uid, :day, :muscle, :name, :sets, :reps, :notes, :gym_id)");
                    foreach ($preset['exercises'] as $ex) {
                        $insTplStmt->execute([
                            ':uid' => $userId,
                            ':day' => $ex['day'],
                            ':muscle' => $ex['muscle'],
                            ':name' => $ex['name'],
                            ':sets' => intval($ex['sets'] ?? 3),
                            ':reps' => trim($ex['reps'] ?? '10-12 reps'),
                            ':notes' => trim($ex['notes'] ?? ''),
                            ':gym_id' => $gymId
                        ]);
                    }
                    $flashSuccess = "Applied preset split: " . htmlspecialchars($preset['title']) . " (" . count($preset['exercises']) . " exercises scheduled)!";
                } else {
                    $flashError = "Preset split not found or has no exercises.";
                }
            }
        }

        // ----------------------------------------------------------------------
        // Action H: Save Current Routine as Custom Preset
        // ----------------------------------------------------------------------
        elseif ($action === 'save_current_as_preset') {
            $pTitle = trim($_POST['preset_title'] ?? '');
            $pTag = trim($_POST['preset_tag'] ?? 'Custom Split');
            $pDiff = trim($_POST['preset_difficulty'] ?? 'Intermediate');
            $pDesc = trim($_POST['preset_description'] ?? '');

            if (empty($pTitle)) {
                $flashError = "Please specify a title for your custom preset.";
            } else {
                $wStmt = $dbh->prepare("SELECT day_of_week as day, target_muscle as muscle, exercise_name as name, sets, reps, notes FROM tbl_workout_plans WHERE user_id = :uid ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), id ASC");
                $wStmt->execute([':uid' => $userId]);
                $currExs = $wStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($currExs)) {
                    $flashError = "Your weekly schedule is currently empty. Add exercises first before saving as a preset.";
                } else {
                    $daysCount = count(array_unique(array_column($currExs, 'day')));
                    $insPreset = $dbh->prepare("INSERT INTO tbl_workout_presets 
                        (user_id, gym_id, title, tag, difficulty, days_count, description, exercises_json, is_system)
                        VALUES 
                        (:uid, :gym_id, :title, :tag, :diff, :days_count, :desc, :json, 0)");
                    $insPreset->execute([
                        ':uid' => $userId,
                        ':gym_id' => $gymId,
                        ':title' => $pTitle,
                        ':tag' => $pTag ?: 'Custom Split',
                        ':diff' => in_array($pDiff, ['Beginner', 'Intermediate', 'Advanced']) ? $pDiff : 'Intermediate',
                        ':days_count' => $daysCount,
                        ':desc' => $pDesc,
                        ':json' => json_encode($currExs, JSON_UNESCAPED_UNICODE)
                    ]);
                    $flashSuccess = "Saved current schedule as '{$pTitle}' custom preset!";
                }
            }
        }

        // ----------------------------------------------------------------------
        // Action I: Create Brand New Custom Preset
        // ----------------------------------------------------------------------
        elseif ($action === 'create_custom_preset') {
            $pTitle = trim($_POST['preset_title'] ?? '');
            $pTag = trim($_POST['preset_tag'] ?? 'Custom Split');
            $pDiff = trim($_POST['preset_difficulty'] ?? 'Intermediate');
            $pDesc = trim($_POST['preset_description'] ?? '');
            $rawExercises = $_POST['preset_exercises_json'] ?? '[]';
            $exercises = json_decode($rawExercises, true);

            if (empty($pTitle)) {
                $flashError = "Please enter a preset title.";
            } elseif (!is_array($exercises) || empty($exercises)) {
                $flashError = "Please add at least one exercise to your custom split.";
            } else {
                $cleanExs = [];
                $days = [];
                foreach ($exercises as $ex) {
                    $day = trim($ex['day'] ?? 'Monday');
                    $muscle = trim($ex['muscle'] ?? 'Chest');
                    $name = trim($ex['name'] ?? '');
                    $sets = max(1, intval($ex['sets'] ?? 3));
                    $reps = trim($ex['reps'] ?? '10-12 reps');
                    $notes = trim($ex['notes'] ?? '');
                    if ($name !== '') {
                        $cleanExs[] = ['day' => $day, 'muscle' => $muscle, 'name' => $name, 'sets' => $sets, 'reps' => $reps, 'notes' => $notes];
                        $days[$day] = true;
                    }
                }
                if (empty($cleanExs)) {
                    $flashError = "Please provide valid exercise names.";
                } else {
                    $insPreset = $dbh->prepare("INSERT INTO tbl_workout_presets 
                        (user_id, gym_id, title, tag, difficulty, days_count, description, exercises_json, is_system)
                        VALUES 
                        (:uid, :gym_id, :title, :tag, :diff, :days_count, :desc, :json, 0)");
                    $insPreset->execute([
                        ':uid' => $userId,
                        ':gym_id' => $gymId,
                        ':title' => $pTitle,
                        ':tag' => $pTag ?: 'Custom Split',
                        ':diff' => in_array($pDiff, ['Beginner', 'Intermediate', 'Advanced']) ? $pDiff : 'Intermediate',
                        ':days_count' => count($days),
                        ':desc' => $pDesc,
                        ':json' => json_encode($cleanExs, JSON_UNESCAPED_UNICODE)
                    ]);
                    $flashSuccess = "Created custom preset '{$pTitle}' with " . count($cleanExs) . " exercises!";
                }
            }
        }

        // ----------------------------------------------------------------------
        // Action J: Edit Custom Preset
        // ----------------------------------------------------------------------
        elseif ($action === 'edit_custom_preset') {
            $presetId = intval($_POST['preset_id'] ?? 0);
            $pTitle = trim($_POST['preset_title'] ?? '');
            $pTag = trim($_POST['preset_tag'] ?? 'Custom Split');
            $pDiff = trim($_POST['preset_difficulty'] ?? 'Intermediate');
            $pDesc = trim($_POST['preset_description'] ?? '');
            $rawExercises = $_POST['preset_exercises_json'] ?? '[]';
            $exercises = json_decode($rawExercises, true);

            $chkStmt = $dbh->prepare("SELECT id FROM tbl_workout_presets WHERE id = :id AND user_id = :uid");
            $chkStmt->execute([':id' => $presetId, ':uid' => $userId]);
            if (!$chkStmt->fetch()) {
                $flashError = "Unauthorized: You can only edit your own custom presets.";
            } elseif (empty($pTitle)) {
                $flashError = "Preset title cannot be empty.";
            } elseif (!is_array($exercises) || empty($exercises)) {
                $flashError = "Please keep at least one exercise in the preset.";
            } else {
                $cleanExs = [];
                $days = [];
                foreach ($exercises as $ex) {
                    $day = trim($ex['day'] ?? 'Monday');
                    $muscle = trim($ex['muscle'] ?? 'Chest');
                    $name = trim($ex['name'] ?? '');
                    $sets = max(1, intval($ex['sets'] ?? 3));
                    $reps = trim($ex['reps'] ?? '10-12 reps');
                    $notes = trim($ex['notes'] ?? '');
                    if ($name !== '') {
                        $cleanExs[] = ['day' => $day, 'muscle' => $muscle, 'name' => $name, 'sets' => $sets, 'reps' => $reps, 'notes' => $notes];
                        $days[$day] = true;
                    }
                }
                $updStmt = $dbh->prepare("UPDATE tbl_workout_presets SET 
                    title = :title,
                    tag = :tag,
                    difficulty = :diff,
                    days_count = :days_count,
                    description = :desc,
                    exercises_json = :json
                    WHERE id = :id AND user_id = :uid");
                $updStmt->execute([
                    ':title' => $pTitle,
                    ':tag' => $pTag ?: 'Custom Split',
                    ':diff' => in_array($pDiff, ['Beginner', 'Intermediate', 'Advanced']) ? $pDiff : 'Intermediate',
                    ':days_count' => count($days),
                    ':desc' => $pDesc,
                    ':json' => json_encode($cleanExs, JSON_UNESCAPED_UNICODE),
                    ':id' => $presetId,
                    ':uid' => $userId
                ]);
                $flashSuccess = "Updated custom preset '{$pTitle}' successfully!";
            }
        }

        // ----------------------------------------------------------------------
        // Action K: Delete Custom Preset
        // ----------------------------------------------------------------------
        elseif ($action === 'delete_custom_preset') {
            $presetId = intval($_POST['preset_id'] ?? 0);
            $delStmt = $dbh->prepare("DELETE FROM tbl_workout_presets WHERE id = :id AND user_id = :uid");
            $delStmt->execute([':id' => $presetId, ':uid' => $userId]);
            $flashSuccess = "Custom split preset deleted.";
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

// D. Member Weekly Training Routine
$workoutStmt = $dbh->prepare("
    SELECT * FROM tbl_workout_plans 
    WHERE user_id = :uid 
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), id ASC
");
$workoutStmt->execute([':uid' => $userId]);
$userWorkouts = $workoutStmt->fetchAll(PDO::FETCH_ASSOC);

$weeklyWorkouts = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => [],
    'Sunday' => []
];
$totalWeeklySets = 0;
foreach ($userWorkouts as $w) {
    $weeklyWorkouts[$w['day_of_week']][] = $w;
    $totalWeeklySets += intval($w['sets']);
}
$totalWeeklyExercises = count($userWorkouts);
$activeWorkoutDaysCount = count(array_filter($weeklyWorkouts, fn($dayArr) => !empty($dayArr)));
$todayDayOfWeek = date('l');
$todayExercises = $weeklyWorkouts[$todayDayOfWeek] ?? [];

$masterExercises = get_master_exercise_catalog();
$exerciseCategories = get_exercise_categories();
$exerciseMuscleGroups = get_exercise_muscle_groups();
$systemWorkoutPresets = get_system_workout_presets();
$userWorkoutPresets = get_user_workout_presets($userId, $dbh);

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
    
    /* Weekly Routine & Planner Styles */
    .routine-day-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 22px 24px;
      margin-bottom: 20px;
      transition: all 0.2s ease;
    }
    .routine-day-card.is-today {
      border-color: rgba(56, 189, 248, 0.45);
      background: rgba(15, 23, 42, 0.85);
      box-shadow: 0 0 24px rgba(56, 189, 248, 0.12);
    }
    .routine-day-card:hover {
      border-color: rgba(255, 255, 255, 0.16);
    }
    .exercise-item-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 16px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: 12px;
      margin-bottom: 8px;
      transition: all 0.15s ease;
    }
    .exercise-item-row:hover {
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(255, 255, 255, 0.1);
    }
    .routine-filter-chip {
      background: rgba(255, 255, 255, 0.05);
      color: #94a3b8;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-block;
    }
    .routine-filter-chip:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.1);
    }
    .routine-filter-chip.active {
      background: var(--color-primary);
      color: #04101e;
      border-color: var(--color-primary);
      font-weight: 700;
    }
    .modal-content.ios-modal {
      background: #0f172a;
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 20px;
      box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.9);
      color: #f8fafc;
    }
    .modal-content.ios-modal .modal-header {
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding: 18px 24px;
    }
    .modal-content.ios-modal .modal-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      padding: 16px 24px;
    }

    /* ==========================================================================
       FITFORGE RESPONSIVE ENHANCEMENTS FOR PHONE & TABLET DEVICES
       ========================================================================== */
    @media (max-width: 991.98px) {
      .modal-dialog.modal-xl,
      .modal-dialog.modal-lg {
        max-width: 96% !important;
        margin: 15px auto !important;
      }
    }

    @media (max-width: 767.98px) {
      /* Mobile Modals */
      .modal-dialog {
        margin: 8px auto !important;
        width: calc(100% - 16px) !important;
        max-width: 100% !important;
      }
      .modal-content.ios-modal {
        border-radius: 16px !important;
      }
      .modal-content.ios-modal .modal-header {
        padding: 14px 16px !important;
      }
      .modal-content.ios-modal .modal-header h4 {
        font-size: 16px !important;
        line-height: 1.3 !important;
      }
      .modal-content.ios-modal .modal-body {
        padding: 14px 16px !important;
      }
      .modal-content.ios-modal .modal-footer {
        padding: 12px 16px !important;
      }

      /* Preset Hub Toolbar */
      .preset-hub-toolbar {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 12px !important;
        gap: 12px !important;
      }
      .preset-hub-left {
        width: 100% !important;
        min-width: 0 !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
      }
      .preset-hub-search-wrapper {
        max-width: 100% !important;
        width: 100% !important;
      }
      .preset-filter-chips-container {
        overflow-x: auto !important;
        white-space: nowrap !important;
        padding-bottom: 4px !important;
        -webkit-overflow-scrolling: touch !important;
        width: 100% !important;
        scrollbar-width: none !important;
      }
      .preset-filter-chips-container::-webkit-scrollbar {
        display: none !important;
      }
      .preset-hub-actions {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
        width: 100% !important;
      }
      .preset-hub-actions button {
        width: 100% !important;
        text-align: center !important;
        justify-content: center !important;
        padding: 8px 6px !important;
        font-size: 11.5px !important;
      }

      /* Preview Modal Footer */
      .preview-modal-footer {
        flex-direction: column-reverse !important;
        align-items: stretch !important;
        gap: 8px !important;
        padding: 12px 16px !important;
      }
      .preview-modal-footer form,
      .preview-modal-footer button {
        width: 100% !important;
      }
      .preview-modal-footer .ios-btn-primary {
        padding: 10px !important;
        font-size: 13px !important;
      }

      /* Routine Section Header Actions */
      .routine-header-actions {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 8px !important;
        width: 100% !important;
        margin-top: 14px !important;
      }
      .routine-header-actions button {
        width: 100% !important;
        margin: 0 !important;
        padding: 8px 6px !important;
        font-size: 12px !important;
        text-align: center !important;
        justify-content: center !important;
      }

      /* Preset Builder Table transformed into mobile touch-cards */
      #builderExercisesTable thead {
        display: none !important;
      }
      #builderExercisesTable,
      #builderExercisesTable tbody,
      .builder-exercise-row {
        display: block !important;
        width: 100% !important;
      }
      .builder-exercise-row {
        background: rgba(255, 255, 255, 0.03) !important;
        border: 1px solid rgba(56, 189, 248, 0.22) !important;
        border-radius: 14px !important;
        padding: 14px !important;
        margin-bottom: 12px !important;
      }
      .builder-exercise-row td {
        display: block !important;
        width: 100% !important;
        padding: 4px 0 !important;
        border: none !important;
      }
      .builder-exercise-row td::before {
        content: attr(data-label);
        display: block;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 3px;
        letter-spacing: 0.5px;
      }
      .builder-exercise-row td[data-label="Remove"]::before {
        display: none !important;
      }
      .builder-exercise-row td[data-label="Remove"] {
        padding-top: 8px !important;
        border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
        margin-top: 6px !important;
      }
    }

    @media (max-width: 575.98px) {
      .routine-day-card {
        padding: 16px 14px !important;
      }

      /* Exercise Items in Day Cards */
      .exercise-item-row {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 8px !important;
        padding: 10px 12px !important;
      }
      .exercise-item-row > div:first-child {
        width: 100% !important;
        min-width: 0 !important;
      }
      .exercise-item-row > div:last-child {
        width: 100% !important;
        justify-content: space-between !important;
        border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding-top: 8px !important;
      }

      /* Daily Routine Header */
      .routine-day-card > div:first-child {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 8px !important;
      }
      .routine-day-card > div:first-child > div:last-child {
        width: 100% !important;
      }
      .routine-day-card > div:first-child > div:last-child button {
        width: 100% !important;
      }

      /* Profile Overview Snapshot Tiles */
      .profile-snapshot-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
      }

      /* Exercise Catalog Search & Filters */
      #exerciseCatalogSection {
        padding: 18px 16px !important;
      }
      #exerciseCatalogSection .row > div {
        margin-bottom: 8px !important;
      }
    }

    @media (max-width: 380px) {
      .routine-header-actions {
        grid-template-columns: 1fr !important;
      }
      .preset-hub-actions {
        grid-template-columns: 1fr !important;
      }
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
              <button type="button" class="ios-btn" id="btnHeroWorkoutRoutine" style="padding: 6px 14px; font-size: 12px; border-radius: 8px; flex: 1; text-align: center; background: rgba(255,255,255,0.08); color: #fff; cursor: pointer;" onclick="switchProfileTab('tab-routine', null, true)">
                <i class="fa fa-calendar-check-o"></i> Workout Routine
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Responsive Navigation Bar -->
    <div class="profile-nav-tabs no-print" id="profileTabs">
      <a href="#overview" class="profile-tab-btn active" onclick="switchProfileTab('tab-overview', this)">
        <i class="fa fa-dashboard"></i> Overview
      </a>
      <a href="#personal" class="profile-tab-btn" onclick="switchProfileTab('tab-personal', this)">
        <i class="fa fa-user"></i> Personal Info
      </a>
      <a href="#routine" class="profile-tab-btn" onclick="switchProfileTab('tab-routine', this)">
        <i class="fa fa-calendar-check-o"></i> Exercise Routine
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

      <!-- Overview Row 3: Today's Training Schedule & Weekly Routine Snapshot -->
      <div class="row">
        <div class="col-12">
          <div class="ios-glass" style="padding: 24px 28px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
              <div>
                <h3 style="font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 4px;">
                  <i class="fa fa-calendar-check-o" style="color: var(--color-primary); margin-right: 8px;"></i>
                  Today's Training Schedule: <span style="color: var(--color-primary);"><?php echo $todayDayOfWeek; ?></span>
                </h3>
                <p style="font-size: 12.5px; color: #94a3b8; margin: 0;">
                  Weekly split: <?php echo $activeWorkoutDaysCount; ?> training days &bull; <?php echo 7 - $activeWorkoutDaysCount; ?> rest days (<?php echo $totalWeeklyExercises; ?> total exercises)
                </p>
              </div>
              <div>
                <button type="button" class="ios-btn ios-btn-primary" id="btnOverviewCustomizeRoutine" style="padding: 7px 16px; font-size: 12.5px; cursor: pointer;" onclick="switchProfileTab('tab-routine', null, true)">
                  <i class="fa fa-pencil"></i> Customize Weekly Routine
                </button>
              </div>
            </div>

            <?php if (!empty($todayExercises)): ?>
              <div style="background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between;">
                <div>
                  <span style="font-size: 11px; text-transform: uppercase; color: #38bdf8; font-weight: 700; letter-spacing: 0.05em;">Target Focus</span>
                  <div style="font-size: 16px; font-weight: 800; color: #fff;"><?php echo htmlspecialchars($todayExercises[0]['target_muscle']); ?></div>
                </div>
                <div style="text-align: right;">
                  <span class="ios-badge ios-badge-info" style="font-size: 12px;"><?php echo count($todayExercises); ?> Exercises Scheduled</span>
                </div>
              </div>
              <div class="row">
                <?php foreach ($todayExercises as $tEx): ?>
                  <div class="col-md-6 col-lg-4 mb-2">
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
                      <div>
                        <strong style="color: #fff; font-size: 13px;"><?php echo htmlspecialchars($tEx['exercise_name']); ?></strong>
                        <?php if (!empty($tEx['notes'])): ?>
                          <div style="font-size: 11px; color: #94a3b8;"><?php echo htmlspecialchars($tEx['notes']); ?></div>
                        <?php endif; ?>
                      </div>
                      <span style="font-family: monospace; font-size: 12px; color: #38bdf8; font-weight: 700; background: rgba(56,189,248,0.1); padding: 2px 8px; border-radius: 6px;">
                        <?php echo $tEx['sets']; ?> &times; <?php echo htmlspecialchars($tEx['reps']); ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; text-align: center;">
                <i class="fa fa-bed" style="font-size: 24px; color: #64748b; margin-bottom: 8px; display: block;"></i>
                <div style="color: #e2e8f0; font-weight: 700; font-size: 14.5px;">Scheduled Rest &amp; Active Recovery</div>
                <div style="color: #94a3b8; font-size: 12.5px; margin-top: 4px;">No intense lifting scheduled for <?php echo $todayDayOfWeek; ?>. Rest, hydrate, and stretch.</div>
                <div style="margin-top: 12px;">
                  <button type="button" class="btn btn-sm btn-outline-info" style="border-radius: 8px;" onclick="openAddWorkoutModal('<?php echo $todayDayOfWeek; ?>')">
                    <i class="fa fa-plus"></i> Add Workout to Today
                  </button>
                </div>
              </div>
            <?php endif; ?>
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
    <!-- TAB: EXERCISE ROUTINE & WEEKLY SCHEDULE PLANNER -->
    <!-- ======================================================================== -->
    <div id="tab-routine" class="tab-content-panel">
      <!-- Section Header Card -->
      <div class="ios-glass" style="padding: 28px 32px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
          <div>
            <h2 style="font-size: 22px; font-weight: 800; color: #fff; margin: 0 0 6px;">
              <i class="fa fa-calendar-check-o" style="color: var(--color-primary); margin-right: 8px;"></i>
              Weekly Exercise Routine &amp; Schedule
            </h2>
            <p style="font-size: 13.5px; color: #94a3b8; margin: 0;">
              Customize your 7-day training split, add exercises, adjust sets and target reps, or select balanced workout templates.
            </p>
          </div>
          <div class="routine-header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="ios-btn ios-btn-primary" id="btnAddExerciseTop" style="padding: 9px 18px; font-size: 13px; cursor: pointer;" onclick="openAddWorkoutModal()">
              <i class="fa fa-plus-circle"></i> Add Exercise
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" id="btnPresetSplits" onclick="openPresetSplitsModal()" style="border-radius: 10px; padding: 8px 16px; font-size: 13px; background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); color: #fff; cursor: pointer;">
              <i class="fa fa-magic" style="color: #fbbf24; margin-right: 6px;"></i> Preset Workout Splits
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning" id="btnSaveCurrentRoutine" onclick="openSaveCurrentAsPresetModal()" style="border-radius: 10px; padding: 8px 16px; font-size: 13px; background: rgba(251,191,36,0.08); border-color: rgba(251,191,36,0.25); color: #fbbf24; cursor: pointer;">
              <i class="fa fa-floppy-o" style="margin-right: 6px;"></i> Save as Preset
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" id="btnBrowseCatalog" onclick="scrollToExerciseCatalog()" style="border-radius: 10px; padding: 8px 16px; font-size: 13px; background: rgba(56,189,248,0.08); border-color: rgba(56,189,248,0.25); color: #38bdf8; cursor: pointer;">
              <i class="fa fa-book" style="margin-right: 6px;"></i> Browse <?php echo count($masterExercises); ?> Exercises
            </button>
          </div>
        </div>

        <!-- Weekly Summary KPI Metrics -->
        <div class="row" style="margin-top: 22px;">
          <div class="col-sm-6 col-md-3 mb-2">
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 12px 16px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; display: block;">Total Exercises</span>
              <span style="font-size: 20px; font-weight: 800; color: #fff;"><?php echo $totalWeeklyExercises; ?> planned</span>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-2">
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 12px 16px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; display: block;">Weekly Volume</span>
              <span style="font-size: 20px; font-weight: 800; color: #38bdf8;"><?php echo $totalWeeklySets; ?> total sets</span>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-2">
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 12px 16px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; display: block;">Training Split</span>
              <span style="font-size: 20px; font-weight: 800; color: #34d399;"><?php echo $activeWorkoutDaysCount; ?> active / <?php echo 7 - $activeWorkoutDaysCount; ?> rest</span>
            </div>
          </div>
          <div class="col-sm-6 col-md-3 mb-2">
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 12px 16px;">
              <span style="font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; display: block;">Today's Focus (<?php echo $todayDayOfWeek; ?>)</span>
              <span style="font-size: 15px; font-weight: 800; color: #fbbf24; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                <?php echo !empty($todayExercises) ? htmlspecialchars($todayExercises[0]['target_muscle']) : 'Rest & Recovery'; ?>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Days Filter Chips -->
      <div style="margin-bottom: 20px; overflow-x: auto; white-space: nowrap; padding-bottom: 6px;">
        <span class="routine-filter-chip active" onclick="filterRoutineDays('all', this)">All Days</span>
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $dayName): ?>
          <?php 
            $count = count($weeklyWorkouts[$dayName]);
            $isToday = ($dayName === $todayDayOfWeek);
          ?>
          <span class="routine-filter-chip" onclick="filterRoutineDays('<?php echo $dayName; ?>', this)">
            <?php echo substr($dayName, 0, 3); ?> (<?php echo $count; ?>)<?php echo $isToday ? ' &bull; Today' : ''; ?>
          </span>
        <?php endforeach; ?>
      </div>

      <!-- 7-Day Routine Cards Container -->
      <div class="row">
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
          <?php 
            $exercises = $weeklyWorkouts[$day];
            $hasExercises = !empty($exercises);
            $isToday = ($day === $todayDayOfWeek);
            $daySets = array_sum(array_column($exercises, 'sets'));
          ?>
          <div class="col-12 routine-day-card <?php echo $isToday ? 'is-today' : ''; ?>" data-day="<?php echo $day; ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h3 style="font-size: 18px; font-weight: 800; color: #fff; margin: 0;">
                  <?php echo $day; ?>
                </h3>
                <?php if ($isToday): ?>
                  <span class="ios-badge ios-badge-success" style="font-size: 11px;">TODAY</span>
                <?php endif; ?>
                <?php if ($hasExercises): ?>
                  <span class="ios-badge ios-badge-info" style="font-size: 11.5px;">
                    <?php echo htmlspecialchars($exercises[0]['target_muscle']); ?>
                  </span>
                  <span style="font-size: 12px; color: #94a3b8;">
                    <?php echo count($exercises); ?> exercises &bull; <?php echo $daySets; ?> sets
                  </span>
                <?php else: ?>
                  <span class="ios-badge" style="background: rgba(255,255,255,0.06); color: #94a3b8; font-size: 11px;">
                    Rest &amp; Recovery
                  </span>
                <?php endif; ?>
              </div>
              <div>
                <?php $suggestedMuscle = $hasExercises ? $exercises[0]['target_muscle'] : 'Chest'; ?>
                <button type="button" class="btn btn-sm btn-outline-info" style="border-radius: 8px; font-size: 12px; cursor: pointer;" onclick="openAddWorkoutModal('<?php echo $day; ?>', '<?php echo addslashes($suggestedMuscle); ?>')">
                  <i class="fa fa-plus"></i> Add Exercise
                </button>
              </div>
            </div>

            <?php if ($hasExercises): ?>
              <div class="exercise-list-group">
                <?php foreach ($exercises as $ex): ?>
                  <div class="exercise-item-row">
                    <div style="flex: 1; min-width: 200px;">
                      <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <strong style="color: #fff; font-size: 14px;"><?php echo htmlspecialchars($ex['exercise_name']); ?></strong>
                        <span style="font-size: 10.5px; background: rgba(56,189,248,0.12); color: #38bdf8; padding: 2px 8px; border-radius: 6px;">
                          <?php echo htmlspecialchars($ex['target_muscle']); ?>
                        </span>
                      </div>
                      <?php if (!empty($ex['notes'])): ?>
                        <div style="font-size: 12px; color: #94a3b8; margin-top: 3px;">
                          <i class="fa fa-info-circle" style="color: #64748b; margin-right: 4px;"></i><?php echo htmlspecialchars($ex['notes']); ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div style="display: flex; align-items: center; gap: 14px;">
                      <div style="font-family: monospace; font-size: 13.5px; font-weight: 700; color: #38bdf8; background: rgba(56,189,248,0.08); padding: 5px 12px; border-radius: 8px;">
                        <?php echo $ex['sets']; ?> sets &times; <?php echo htmlspecialchars($ex['reps']); ?>
                      </div>
                      <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-sm btn-outline-light" style="padding: 4px 8px; font-size: 12px; border-radius: 6px; border-color: rgba(255,255,255,0.15);" onclick="openEditWorkoutModal(<?php echo $ex['id']; ?>, '<?php echo addslashes($ex['day_of_week']); ?>', '<?php echo addslashes($ex['target_muscle']); ?>', '<?php echo addslashes($ex['exercise_name']); ?>', <?php echo intval($ex['sets']); ?>, '<?php echo addslashes($ex['reps']); ?>', '<?php echo addslashes($ex['notes'] ?? ''); ?>')">
                          <i class="fa fa-pencil"></i>
                        </button>
                        <form method="post" style="display: inline;" onsubmit="return confirm('Remove <?php echo addslashes($ex['exercise_name']); ?> from <?php echo $day; ?> routine?');">
                          <?php echo csrf_input(); ?>
                          <input type="hidden" name="action" value="delete_workout">
                          <input type="hidden" name="workout_id" value="<?php echo $ex['id']; ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 4px 8px; font-size: 12px; border-radius: 6px;">
                            <i class="fa fa-trash-o"></i>
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div style="text-align: center; padding: 22px 16px; background: rgba(255,255,255,0.015); border: 1px dashed rgba(255,255,255,0.07); border-radius: 12px; color: #64748b;">
                <i class="fa fa-bed" style="font-size: 22px; color: #475569; display: block; margin-bottom: 6px;"></i>
                <span style="font-size: 13px;">No workouts scheduled for <?php echo $day; ?>. Scheduled Rest &amp; Muscle Recovery Day.</span>
                <div style="margin-top: 10px;">
                  <button type="button" class="ios-btn ios-btn-primary" style="padding: 5px 14px; font-size: 12px;" onclick="openAddWorkoutModal('<?php echo $day; ?>')">
                    <i class="fa fa-plus"></i> Schedule Workout
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ==================================================================== -->
      <!-- MASTER EXERCISE LIBRARY & EQUIPMENT DIRECTORY -->
      <!-- ==================================================================== -->
      <div id="exerciseCatalogSection" class="ios-glass" style="padding: 28px 32px; margin-top: 30px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
          <div>
            <h3 style="font-size: 20px; font-weight: 800; color: #fff; margin: 0 0 6px;">
              <i class="fa fa-book" style="color: var(--color-primary); margin-right: 8px;"></i>
              Master Exercise Library &amp; Equipment Catalog
            </h3>
            <p style="font-size: 13px; color: #94a3b8; margin: 0;">
              Browse all <?php echo count($masterExercises); ?>+ verified exercises sorted by category: Gym Floor, Treadmill &amp; Cardio, Dumbbells, Barbells, Machines, and Functional drills. Click <strong>+ Add to Routine</strong> on any movement to schedule it.
            </p>
          </div>
          <span class="ios-badge ios-badge-info" id="catalogCountBadge" style="font-size: 12px; padding: 6px 14px;">
            <?php echo count($masterExercises); ?> Exercises Available
          </span>
        </div>

        <!-- Filter & Search Controls -->
        <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 18px 20px; margin-bottom: 22px;">
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="ios-label" style="font-size: 11px; text-transform: uppercase;">Search Movement / Equipment</label>
              <div style="position: relative;">
                <input type="text" id="catalogSearchInput" class="ios-input" placeholder="Type exercise name, muscle, or equipment (e.g., Incline, Cable, Bench)..." oninput="filterCatalogExercises()" style="padding-left: 36px;">
                <i class="fa fa-search" style="position: absolute; left: 14px; top: 13px; color: #64748b; font-size: 14px;"></i>
              </div>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
              <label class="ios-label" style="font-size: 11px; text-transform: uppercase;">Filter by Muscle Group</label>
              <select id="catalogMuscleFilter" class="ios-input" onchange="filterCatalogExercises()">
                <?php foreach ($exerciseMuscleGroups as $mKey => $mLabel): ?>
                  <option value="<?php echo htmlspecialchars($mKey); ?>"><?php echo htmlspecialchars($mLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6 col-md-3 mb-2">
              <label class="ios-label" style="font-size: 11px; text-transform: uppercase;">Sort Order</label>
              <select id="catalogSortOrder" class="ios-input" onchange="sortCatalogExercises()">
                <option value="category">Category Order</option>
                <option value="name_asc">Name (A &rarr; Z)</option>
                <option value="muscle">Target Muscle</option>
                <option value="difficulty">Difficulty Level</option>
              </select>
            </div>
          </div>

          <!-- Category Quick Filter Chips -->
          <div style="margin-top: 14px; overflow-x: auto; white-space: nowrap; padding-bottom: 4px;">
            <span class="catalog-filter-btn active" data-cat="all" onclick="setCatalogCategory('all', this)">All Categories (<?php echo count($masterExercises); ?>)</span>
            <span class="catalog-filter-btn" data-cat="floor" onclick="setCatalogCategory('floor', this)"><i class="fa fa-user"></i> Gym Floor / Bodyweight</span>
            <span class="catalog-filter-btn" data-cat="cardio" onclick="setCatalogCategory('cardio', this)"><i class="fa fa-heartbeat"></i> Treadmill &amp; Cardio</span>
            <span class="catalog-filter-btn" data-cat="dumbbells" onclick="setCatalogCategory('dumbbells', this)"><i class="fa fa-bullseye"></i> Free Weights (Dumbbells)</span>
            <span class="catalog-filter-btn" data-cat="barbell" onclick="setCatalogCategory('barbell', this)"><i class="fa fa-bars"></i> Free Weights (Barbell)</span>
            <span class="catalog-filter-btn" data-cat="machines" onclick="setCatalogCategory('machines', this)"><i class="fa fa-cogs"></i> Machines &amp; Cables</span>
            <span class="catalog-filter-btn" data-cat="functional" onclick="setCatalogCategory('functional', this)"><i class="fa fa-refresh"></i> Functional &amp; Mobility</span>
          </div>
        </div>

        <!-- Exercise Cards Container Grid -->
        <div class="row" id="catalogCardsContainer">
          <?php foreach ($masterExercises as $ex): ?>
            <?php 
              // Distinct theme colors per category
              $catColor = '#38bdf8';
              $catBg = 'rgba(56,189,248,0.12)';
              if ($ex['category_key'] === 'floor') {
                  $catColor = '#c084fc';
                  $catBg = 'rgba(192,132,252,0.14)';
              } elseif ($ex['category_key'] === 'cardio') {
                  $catColor = '#f87171';
                  $catBg = 'rgba(248,113,113,0.14)';
              } elseif ($ex['category_key'] === 'dumbbells') {
                  $catColor = '#38bdf8';
                  $catBg = 'rgba(56,189,248,0.14)';
              } elseif ($ex['category_key'] === 'barbell') {
                  $catColor = '#fbbf24';
                  $catBg = 'rgba(251,191,36,0.14)';
              } elseif ($ex['category_key'] === 'machines') {
                  $catColor = '#34d399';
                  $catBg = 'rgba(52,211,153,0.14)';
              } elseif ($ex['category_key'] === 'functional') {
                  $catColor = '#f472b6';
                  $catBg = 'rgba(244,114,182,0.14)';
              }
            ?>
            <div class="col-md-6 col-lg-4 mb-3 catalog-item" 
                 data-category="<?php echo $ex['category_key']; ?>" 
                 data-muscle="<?php echo $ex['muscle_group']; ?>" 
                 data-name="<?php echo strtolower(htmlspecialchars($ex['name'])); ?>" 
                 data-equipment="<?php echo strtolower(htmlspecialchars($ex['equipment'])); ?>"
                 data-difficulty="<?php echo $ex['difficulty']; ?>">
              <div class="exercise-catalog-card">
                <div>
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 11px; font-weight: 700; color: <?php echo $catColor; ?>; background: <?php echo $catBg; ?>; padding: 2px 8px; border-radius: 6px;">
                      <?php echo htmlspecialchars($ex['category']); ?>
                    </span>
                    <span style="font-size: 10.5px; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px;">
                      <?php echo $ex['difficulty']; ?>
                    </span>
                  </div>

                  <h4 style="font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 6px;">
                    <?php echo htmlspecialchars($ex['name']); ?>
                  </h4>

                  <div style="font-size: 12px; color: #38bdf8; font-weight: 600; margin-bottom: 6px;">
                    <i class="fa fa-crosshairs" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($ex['target_muscle']); ?>
                  </div>

                  <div style="font-size: 11.5px; color: #94a3b8; margin-bottom: 10px;">
                    <i class="fa fa-wrench" style="margin-right: 4px; color: #64748b;"></i> Equipment: <span style="color: #cbd5e1;"><?php echo htmlspecialchars($ex['equipment']); ?></span>
                  </div>

                  <div style="font-size: 11.5px; color: #94a3b8; background: rgba(255,255,255,0.02); border-left: 2px solid <?php echo $catColor; ?>; padding: 6px 10px; margin-bottom: 12px; line-height: 1.4;">
                    <?php echo htmlspecialchars($ex['form_cue']); ?>
                  </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.06);">
                  <div style="font-family: monospace; font-size: 12px; color: #34d399; font-weight: 700;">
                    <?php echo $ex['recommended_sets']; ?> sets &times; <?php echo $ex['recommended_reps']; ?>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-info" style="border-radius: 8px; font-size: 11.5px; padding: 3px 10px;" onclick="quickAddFromCatalog('<?php echo addslashes($ex['name']); ?>', '<?php echo addslashes($ex['target_muscle']); ?>', <?php echo $ex['recommended_sets']; ?>, '<?php echo addslashes($ex['recommended_reps']); ?>', '<?php echo addslashes($ex['form_cue']); ?>')">
                    <i class="fa fa-plus"></i> Add to Routine
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="catalogEmptyMessage" style="display: none; text-align: center; padding: 40px 20px; color: #64748b;">
          <i class="fa fa-search" style="font-size: 28px; color: #475569; display: block; margin-bottom: 10px;"></i>
          <span style="font-size: 14px; color: #94a3b8;">No exercises match your search or filter criteria.</span>
          <div style="margin-top: 10px;">
            <button type="button" class="btn btn-sm btn-outline-light" onclick="resetCatalogFilters()" style="border-radius: 8px;">
              Reset Filters
            </button>
          </div>
        </div>
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

        <!-- Weekly Training Routine Summary in Report -->
        <?php if (!empty($userWorkouts)): ?>
          <div style="margin-bottom: 28px;">
            <h4 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.04em;">
              <i class="fa fa-calendar-check-o" style="color: var(--color-primary); margin-right: 6px;"></i>
              Weekly Training &amp; Exercise Routine
            </h4>
            <div class="row">
              <?php foreach ($weeklyWorkouts as $repDay => $repExercises): ?>
                <?php if (!empty($repExercises)): ?>
                  <div class="col-md-6 mb-3">
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 16px;">
                      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong style="color: var(--color-primary); font-size: 14px;"><?php echo $repDay; ?></strong>
                        <span style="font-size: 11px; background: rgba(56,189,248,0.15); color: #38bdf8; padding: 2px 8px; border-radius: 6px;">
                          <?php echo htmlspecialchars($repExercises[0]['target_muscle']); ?>
                        </span>
                      </div>
                      <div style="font-size: 12.5px; color: #cbd5e1;">
                        <?php foreach ($repExercises as $repEx): ?>
                          <div style="display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px solid rgba(255,255,255,0.04);">
                            <span>&bull; <?php echo htmlspecialchars($repEx['exercise_name']); ?></span>
                            <span style="color: #94a3b8; font-family: monospace; font-size: 11.5px;"><?php echo $repEx['sets']; ?> &times; <?php echo htmlspecialchars($repEx['reps']); ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
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

  <!-- ======================================================================== -->
  <!-- MODALS: ADD & EDIT EXERCISES -->
  <!-- ======================================================================== -->
  <datalist id="exerciseSuggestions">
    <option value="Barbell Bench Press">
    <option value="Incline Dumbbell Press">
    <option value="Cable Chest Fly">
    <option value="Dips">
    <option value="Push-ups">
    <option value="Barbell Back Squat">
    <option value="Romanian Deadlift">
    <option value="Leg Press">
    <option value="Leg Extension">
    <option value="Hamstring Leg Curl">
    <option value="Standing Calf Raises">
    <option value="Walking Lunges">
    <option value="Deadlift">
    <option value="Pull-ups / Chin-ups">
    <option value="Lat Pulldown">
    <option value="Barbell Bent Over Row">
    <option value="Seated Cable Row">
    <option value="Dumbbell Single-Arm Row">
    <option value="Overhead Military Press">
    <option value="Dumbbell Lateral Raise">
    <option value="Face Pulls">
    <option value="Barbell Bicep Curl">
    <option value="Dumbbell Hammer Curl">
    <option value="Preacher Curl">
    <option value="Cable Tricep Pushdown">
    <option value="Skull Crushers">
    <option value="Overhead Tricep Extension">
    <option value="Plank Hold">
    <option value="Hanging Leg Raises">
    <option value="Abdominal Cable Crunches">
    <option value="Treadmill Incline Walk / Run">
    <option value="Rowing Machine HIIT">
    <option value="Stairmaster Cardio">
  </datalist>

  <!-- Add Workout Modal -->
  <div class="modal fade" id="addWorkoutModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content ios-modal">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
          <h4 class="modal-title" style="font-weight: 800; font-size: 18px; color: #fff; margin: 0;">
            <i class="fa fa-plus-circle" style="color: var(--color-primary); margin-right: 6px;"></i> Add Exercise to Routine
          </h4>
          <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('addWorkoutModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent;">&times;</button>
        </div>
        <form method="post" action="profile.php#routine">
          <div class="modal-body" style="padding: 24px;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="add_workout">

            <div class="row">
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Day of Week <span style="color: #f87171;">*</span></label>
                  <select name="day_of_week" id="addWorkoutDay" class="ios-input" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Target Muscle Focus <span style="color: #f87171;">*</span></label>
                  <select name="target_muscle" id="addWorkoutMuscle" class="ios-input" onchange="filterModalExercisesByMuscle(this.value)" required>
                    <option value="Chest" selected>Chest (Pectorals)</option>
                    <option value="Back">Back (Lats, Traps &amp; Rhomboids)</option>
                    <option value="Legs">Legs (Quads, Calves, Hamstrings &amp; Glutes)</option>
                    <option value="Shoulders">Shoulders (Deltoids)</option>
                    <option value="Biceps">Biceps &amp; Forearms</option>
                    <option value="Triceps">Triceps</option>
                    <option value="Abs &amp; Core">Abs &amp; Core</option>
                    <option value="Cardio">Cardio &amp; Conditioning</option>
                    <option value="Functional &amp; Mobility">Functional &amp; Mobility</option>
                    <option value="all">All Muscles (Show Complete <?php echo count($masterExercises); ?> Exercise Library)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Contextual Filtered Exercise Quick Selector (Shows only matching muscle exercises) -->
            <div class="ios-form-group" style="background: rgba(56, 189, 248, 0.05); padding: 14px 16px; border: 1px solid rgba(56, 189, 248, 0.22); border-radius: 14px; margin-bottom: 16px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label class="ios-label" style="margin: 0; color: #38bdf8; font-weight: 700;">
                  <i class="fa fa-magic"></i> Exercise Selection (Filtered by Muscle)
                </label>
                <span id="catalogQuickCount" style="color: #38bdf8; font-size: 11.5px; font-weight: 700;"></span>
              </div>
              <select id="catalogQuickSelect" class="ios-input" onchange="applyCatalogSelection(this.value)" style="border-color: rgba(56,189,248,0.35);">
                <!-- Populated dynamically by filterModalExercisesByMuscle() -->
              </select>
              <small style="color: #94a3b8; font-size: 11px; display: block; margin-top: 4px;">
                Selecting an exercise above auto-populates exercise name, recommended sets, reps, and coaching cues below.
              </small>
            </div>

            <div class="ios-form-group">
              <label class="ios-label">Exercise Name <span style="color: #f87171;">*</span></label>
              <input type="text" name="exercise_name" id="addWorkoutName" class="ios-input" list="exerciseSuggestions" placeholder="e.g. Barbell Bench Press" required>
              <small style="color: #64748b; font-size: 11px;">Select from suggestions or type your custom exercise.</small>
            </div>

            <div class="row">
              <div class="col-sm-4">
                <div class="ios-form-group">
                  <label class="ios-label">Sets <span style="color: #f87171;">*</span></label>
                  <input type="number" name="sets" id="addWorkoutSets" class="ios-input" min="1" max="20" value="3" required>
                </div>
              </div>
              <div class="col-sm-8">
                <div class="ios-form-group">
                  <label class="ios-label">Target Reps / Cadence <span style="color: #f87171;">*</span></label>
                  <input type="text" name="reps" id="addWorkoutReps" class="ios-input" value="10-12 reps" placeholder="e.g. 8-10 reps, 15 reps, To Failure" required>
                </div>
              </div>
            </div>

            <div class="ios-form-group">
              <label class="ios-label">Coaching Notes / Form Cues (Optional)</label>
              <input type="text" name="notes" id="addWorkoutNotes" class="ios-input" placeholder="e.g. Control descent, 90s rest, drop set on last set">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal" onclick="closeCustomModal('addWorkoutModal')" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 8px 20px; font-size: 13px;">
              <i class="fa fa-save"></i> Save Exercise
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Workout Modal -->
  <div class="modal fade" id="editWorkoutModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content ios-modal">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
          <h4 class="modal-title" style="font-weight: 800; font-size: 18px; color: #fff; margin: 0;">
            <i class="fa fa-pencil" style="color: var(--color-primary); margin-right: 6px;"></i> Edit Routine Exercise
          </h4>
          <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('editWorkoutModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent; cursor: pointer;">&times;</button>
        </div>
        <form method="post" action="profile.php#routine">
          <div class="modal-body" style="padding: 24px;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="edit_workout">
            <input type="hidden" name="workout_id" id="editWorkoutId" value="">

            <div class="row">
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Day of Week <span style="color: #f87171;">*</span></label>
                  <select name="day_of_week" id="editWorkoutDay" class="ios-input" required>
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                    <option value="Saturday">Saturday</option>
                    <option value="Sunday">Sunday</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Target Muscle Focus <span style="color: #f87171;">*</span></label>
                  <select name="target_muscle" id="editWorkoutMuscle" class="ios-input" onchange="filterModalExercisesByMuscle(this.value, 'editCatalogQuickSelect', 'editCatalogQuickCount')" required>
                    <option value="Chest">Chest (Pectorals)</option>
                    <option value="Back">Back (Lats, Traps &amp; Rhomboids)</option>
                    <option value="Legs">Legs (Quads, Hamstrings &amp; Calves)</option>
                    <option value="Shoulders">Shoulders (Deltoids)</option>
                    <option value="Biceps">Biceps &amp; Forearms</option>
                    <option value="Triceps">Triceps</option>
                    <option value="Abs &amp; Core">Abs &amp; Core</option>
                    <option value="Cardio">Cardio &amp; Conditioning</option>
                    <option value="Functional &amp; Mobility">Functional &amp; Mobility</option>
                    <option value="all">All Muscles (Show Complete <?php echo count($masterExercises); ?> Exercise Library)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Contextual Filtered Exercise Quick Selector for Edit -->
            <div class="ios-form-group" style="background: rgba(56, 189, 248, 0.05); padding: 14px 16px; border: 1px solid rgba(56, 189, 248, 0.22); border-radius: 14px; margin-bottom: 16px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label class="ios-label" style="margin: 0; color: #38bdf8; font-weight: 700;">
                  <i class="fa fa-refresh"></i> Swap / Choose from Muscle Exercises
                </label>
                <span id="editCatalogQuickCount" style="color: #38bdf8; font-size: 11.5px; font-weight: 700;"></span>
              </div>
              <select id="editCatalogQuickSelect" class="ios-input" onchange="applyEditCatalogSelection(this.value)" style="border-color: rgba(56,189,248,0.35);">
                <!-- Populated dynamically -->
              </select>
            </div>

            <div class="ios-form-group">
              <label class="ios-label">Exercise Name <span style="color: #f87171;">*</span></label>
              <input type="text" name="exercise_name" id="editWorkoutName" class="ios-input" list="exerciseSuggestions" required>
            </div>

            <div class="row">
              <div class="col-sm-4">
                <div class="ios-form-group">
                  <label class="ios-label">Sets <span style="color: #f87171;">*</span></label>
                  <input type="number" name="sets" id="editWorkoutSets" class="ios-input" min="1" max="20" required>
                </div>
              </div>
              <div class="col-sm-8">
                <div class="ios-form-group">
                  <label class="ios-label">Target Reps <span style="color: #f87171;">*</span></label>
                  <input type="text" name="reps" id="editWorkoutReps" class="ios-input" required>
                </div>
              </div>
            </div>

            <div class="ios-form-group">
              <label class="ios-label">Coaching Notes / Form Cues</label>
              <input type="text" name="notes" id="editWorkoutNotes" class="ios-input">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal" onclick="closeCustomModal('editWorkoutModal')" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 8px 20px; font-size: 13px;">
              <i class="fa fa-check"></i> Update Exercise
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Preset Splits Modal (Enhanced Preset Hub) -->
  <div class="modal fade" id="presetSplitsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document" style="max-width: 1140px;">
      <div class="modal-content ios-modal">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 18px 24px;">
          <div>
            <h4 class="modal-title" style="font-weight: 800; font-size: 19px; color: #fff; margin: 0;">
              <i class="fa fa-magic" style="color: #fbbf24; margin-right: 8px;"></i> Workout Split Preset Library &amp; Custom Program Hub
            </h4>
            <p style="font-size: 12.5px; color: #94a3b8; margin: 4px 0 0;">
              Choose from verified training templates or build, edit, and organize your own custom split presets.
            </p>
          </div>
          <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('presetSplitsModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
          <!-- Controls Toolbar: Search, Filters & Action Buttons -->
          <div class="preset-hub-toolbar" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 14px 18px; margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div class="preset-hub-left" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1; min-width: 280px;">
              <div class="preset-hub-search-wrapper" style="position: relative; max-width: 300px; width: 100%;">
                <input type="text" id="presetSearchInput" class="ios-input" placeholder="Search presets by title, tag, or muscle..." oninput="filterPresetCards()" style="padding-left: 34px; font-size: 12.5px; height: 38px;">
                <i class="fa fa-search" style="position: absolute; left: 12px; top: 12px; color: #64748b; font-size: 13px;"></i>
              </div>
              <div class="preset-filter-chips-container" style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" class="preset-filter-chip active" id="chipPresetAll" onclick="setPresetFilter('all', this)" style="padding: 6px 14px; font-size: 12px; border-radius: 20px; border: 1px solid rgba(56,189,248,0.4); background: rgba(56,189,248,0.18); color: #38bdf8; font-weight: 700; cursor: pointer;">
                  All Presets (<?php echo count($systemWorkoutPresets) + count($userWorkoutPresets); ?>)
                </button>
                <button type="button" class="preset-filter-chip" id="chipPresetSystem" onclick="setPresetFilter('system', this)" style="padding: 6px 14px; font-size: 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: #cbd5e1; font-weight: 600; cursor: pointer;">
                  <i class="fa fa-certificate" style="color: #fbbf24; margin-right: 4px;"></i> Verified System (<?php echo count($systemWorkoutPresets); ?>)
                </button>
                <button type="button" class="preset-filter-chip" id="chipPresetCustom" onclick="setPresetFilter('custom', this)" style="padding: 6px 14px; font-size: 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: #cbd5e1; font-weight: 600; cursor: pointer;">
                  <i class="fa fa-user" style="color: #34d399; margin-right: 4px;"></i> My Custom (<span id="userPresetsCountBadge"><?php echo count($userWorkoutPresets); ?></span>)
                </button>
              </div>
            </div>
            <div class="preset-hub-actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
              <button type="button" class="btn btn-sm btn-outline-warning" onclick="openSaveCurrentAsPresetModal()" style="border-radius: 9px; padding: 7px 14px; font-size: 12px; background: rgba(251,191,36,0.08); border-color: rgba(251,191,36,0.3); color: #fbbf24; font-weight: 700; cursor: pointer;">
                <i class="fa fa-floppy-o" style="margin-right: 5px;"></i> Save Current Schedule
              </button>
              <button type="button" class="ios-btn ios-btn-primary" onclick="openCreatePresetModal()" style="border-radius: 9px; padding: 7px 14px; font-size: 12px; font-weight: 700; cursor: pointer;">
                <i class="fa fa-plus-circle" style="margin-right: 5px;"></i> + Create New Preset
              </button>
            </div>
          </div>

          <!-- Presets Grid Container -->
          <div class="row" id="presetsCardsGrid">
            <!-- 1. User Custom Presets -->
            <?php foreach ($userWorkoutPresets as $up): ?>
              <?php 
                $upExCount = count($up['exercises']);
                $upDaysCount = $up['days_count'] ?: count(array_unique(array_column($up['exercises'], 'day')));
              ?>
              <div class="col-md-6 col-lg-4 mb-3 preset-item" data-type="custom" data-search="<?php echo htmlspecialchars(strtolower($up['title'] . ' ' . $up['tag'] . ' ' . $up['difficulty'] . ' ' . $up['description'])); ?>">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(52,211,153,0.35); border-radius: 14px; padding: 18px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                  <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                      <div>
                        <span class="ios-badge" style="background: rgba(52,211,153,0.15); color: #34d399; font-size: 10.5px; margin-bottom: 4px; display: inline-block;">
                          <i class="fa fa-user"></i> My Custom Split
                        </span>
                        <h5 style="color: #fff; font-size: 16px; font-weight: 800; margin: 0; line-height: 1.3;">
                          <?php echo htmlspecialchars($up['title']); ?>
                        </h5>
                      </div>
                      <span class="ios-badge" style="background: rgba(255,255,255,0.08); color: #cbd5e1; font-size: 10px;">
                        <?php echo htmlspecialchars($up['difficulty'] ?: 'Intermediate'); ?>
                      </span>
                    </div>

                    <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;">
                      <span style="font-size: 11px; background: rgba(56,189,248,0.1); color: #38bdf8; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-calendar-check-o"></i> <?php echo $upDaysCount; ?> Active Days
                      </span>
                      <span style="font-size: 11px; background: rgba(251,191,36,0.1); color: #fbbf24; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-list"></i> <?php echo $upExCount; ?> Exercises
                      </span>
                      <span style="font-size: 11px; background: rgba(255,255,255,0.05); color: #94a3b8; padding: 3px 8px; border-radius: 6px;">
                        <?php echo htmlspecialchars($up['tag'] ?: 'Custom Split'); ?>
                      </span>
                    </div>

                    <p style="font-size: 12px; color: #94a3b8; line-height: 1.5; margin: 0 0 14px; min-height: 36px;">
                      <?php echo htmlspecialchars($up['description'] ?: 'Personal training split tailored to your weekly goals.'); ?>
                    </p>
                  </div>

                  <div style="border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; gap: 6px;">
                      <button type="button" class="btn btn-sm btn-outline-info w-50" onclick="previewPreset(<?php echo $up['id']; ?>)" style="border-radius: 8px; font-size: 11.5px; padding: 6px; cursor: pointer;">
                        <i class="fa fa-eye"></i> Preview
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-light w-50" onclick="openEditPresetModal(<?php echo $up['id']; ?>)" style="border-radius: 8px; font-size: 11.5px; padding: 6px; cursor: pointer; background: rgba(255,255,255,0.05);">
                        <i class="fa fa-pencil"></i> Edit Split
                      </button>
                    </div>
                    <div style="display: flex; gap: 6px;">
                      <form method="post" onsubmit="return confirm('Apply \'<?php echo addslashes($up['title']); ?>\' to your weekly routine? This will replace your current schedule.');" style="flex: 1; margin: 0;">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="apply_workout_template">
                        <input type="hidden" name="template_type" value="<?php echo $up['id']; ?>">
                        <button type="submit" class="ios-btn ios-btn-primary w-100" style="padding: 7px; font-size: 12px; border-radius: 8px; cursor: pointer;">
                          <i class="fa fa-check-circle"></i> Apply Split
                        </button>
                      </form>
                      <form method="post" onsubmit="return confirm('Delete this custom split preset? This cannot be undone.');" style="margin: 0;">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="delete_custom_preset">
                        <input type="hidden" name="preset_id" value="<?php echo $up['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-radius: 8px; padding: 7px 10px; font-size: 12px; cursor: pointer;">
                          <i class="fa fa-trash-o"></i>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- 2. Verified System Presets -->
            <?php foreach ($systemWorkoutPresets as $slug => $sp): ?>
              <?php 
                $spExCount = count($sp['exercises']);
                $spDaysCount = $sp['days_count'] ?: 3;
              ?>
              <div class="col-md-6 col-lg-4 mb-3 preset-item" data-type="system" data-search="<?php echo htmlspecialchars(strtolower($sp['title'] . ' ' . $sp['tag'] . ' ' . $sp['difficulty'] . ' ' . $sp['description'])); ?>">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid <?php echo $sp['border_color']; ?>; border-radius: 14px; padding: 18px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                  <div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                      <div>
                        <span class="ios-badge" style="background: <?php echo $sp['badge_bg']; ?>; color: <?php echo $sp['badge_color']; ?>; font-size: 10.5px; margin-bottom: 4px; display: inline-block;">
                          <i class="fa fa-certificate"></i> Verified System
                        </span>
                        <h5 style="color: #fff; font-size: 16px; font-weight: 800; margin: 0; line-height: 1.3;">
                          <?php echo htmlspecialchars($sp['title']); ?>
                        </h5>
                      </div>
                      <span class="ios-badge" style="background: rgba(255,255,255,0.08); color: #cbd5e1; font-size: 10px;">
                        <?php echo htmlspecialchars($sp['difficulty']); ?>
                      </span>
                    </div>

                    <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;">
                      <span style="font-size: 11px; background: rgba(56,189,248,0.1); color: #38bdf8; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-calendar-check-o"></i> <?php echo $spDaysCount; ?> Days
                      </span>
                      <span style="font-size: 11px; background: rgba(251,191,36,0.1); color: #fbbf24; padding: 3px 8px; border-radius: 6px; font-weight: 600;">
                        <i class="fa fa-list"></i> <?php echo $spExCount; ?> Movements
                      </span>
                      <span style="font-size: 11px; background: rgba(255,255,255,0.05); color: #94a3b8; padding: 3px 8px; border-radius: 6px;">
                        <?php echo htmlspecialchars($sp['tag']); ?>
                      </span>
                    </div>

                    <p style="font-size: 12px; color: #94a3b8; line-height: 1.5; margin: 0 0 14px; min-height: 36px;">
                      <?php echo htmlspecialchars($sp['description']); ?>
                    </p>
                  </div>

                  <div style="border-top: 1px solid rgba(255,255,255,0.06); padding-top: 12px; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-info w-50" onclick="previewPreset('<?php echo $slug; ?>')" style="border-radius: 8px; font-size: 12px; padding: 8px; cursor: pointer;">
                      <i class="fa fa-eye"></i> Preview
                    </button>
                    <form method="post" onsubmit="return confirm('Apply \'<?php echo addslashes($sp['title']); ?>\'? This replaces your current weekly schedule.');" style="flex: 1; margin: 0;">
                      <?php echo csrf_input(); ?>
                      <input type="hidden" name="action" value="apply_workout_template">
                      <input type="hidden" name="template_type" value="<?php echo $slug; ?>">
                      <button type="submit" class="ios-btn w-100" style="padding: 8px; font-size: 12px; border-radius: 8px; background: <?php echo $sp['badge_color']; ?>; color: #04101e; font-weight: 700; cursor: pointer;">
                        Apply Split
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- 3. Reset Routine Card -->
            <div class="col-md-6 col-lg-4 mb-3 preset-item" data-type="system" data-search="clear reset empty clean schedule">
              <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(248,113,113,0.3); border-radius: 14px; padding: 18px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h5 style="color: #fff; font-size: 16px; font-weight: 800; margin: 0;">Clear Weekly Schedule</h5>
                    <span class="ios-badge ios-badge-danger" style="font-size: 11px;">Reset</span>
                  </div>
                  <div style="font-size: 12px; color: #94a3b8; line-height: 1.6; margin-bottom: 12px;">
                    Removes all currently scheduled exercises so you can organize your 7-day schedule completely from a clean slate.
                  </div>
                </div>
                <form method="post" onsubmit="return confirm('Clear your entire weekly routine? You can re-add exercises or apply a preset anytime.');" style="margin-top: 14px;">
                  <?php echo csrf_input(); ?>
                  <input type="hidden" name="action" value="apply_workout_template">
                  <input type="hidden" name="template_type" value="clear">
                  <button type="submit" class="btn btn-sm btn-outline-danger" style="padding: 8px; font-size: 12px; border-radius: 8px; width: 100%; cursor: pointer;">
                    <i class="fa fa-trash-o"></i> Clear All Exercises
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="padding: 12px 24px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end;">
          <button type="button" class="btn btn-outline-light" data-dismiss="modal" onclick="closeCustomModal('presetSplitsModal')" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Interactive Preset Preview Modal -->
  <div class="modal fade" id="previewPresetModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
      <div class="modal-content ios-modal">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 18px 24px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <h4 class="modal-title" id="previewPresetTitle" style="font-weight: 800; font-size: 18px; color: #fff; margin: 0;"></h4>
            <span id="previewPresetBadge" class="ios-badge" style="font-size: 11px;"></span>
          </div>
          <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('previewPresetModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px;">
          <p id="previewPresetDesc" style="font-size: 13px; color: #94a3b8; margin-bottom: 16px;"></p>
          <div id="previewPresetStats" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;"></div>
          <div id="previewPresetDaysContainer" style="max-height: 480px; overflow-y: auto; padding-right: 4px;"></div>
        </div>
        <div class="modal-footer preview-modal-footer" style="padding: 14px 24px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center;">
          <button type="button" class="btn btn-outline-light" onclick="closeCustomModal('previewPresetModal'); openPresetSplitsModal();" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">
            &larr; Back to Presets
          </button>
          <form method="post" id="previewApplyForm" onsubmit="return confirm('Apply this preset split? This replaces your current weekly schedule.');" style="margin: 0;">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="apply_workout_template">
            <input type="hidden" name="template_type" id="previewApplyType" value="">
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 8px 20px; font-size: 13px; font-weight: 700; cursor: pointer;">
              <i class="fa fa-check"></i> Apply This Preset to Routine
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Save Current Schedule as Preset Modal -->
  <div class="modal fade" id="saveCurrentAsPresetModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content ios-modal">
        <form method="post" action="profile.php">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" value="save_current_as_preset">
          <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 18px 24px;">
            <h4 class="modal-title" style="font-weight: 800; font-size: 17px; color: #fff; margin: 0;">
              <i class="fa fa-floppy-o" style="color: #fbbf24; margin-right: 8px;"></i> Save Current Schedule as Preset
            </h4>
            <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('saveCurrentAsPresetModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent; cursor: pointer;">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px;">
            <div style="background: rgba(56,189,248,0.06); border: 1px solid rgba(56,189,248,0.2); border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; font-size: 12.5px; color: #94a3b8;">
              <strong style="color: #38bdf8;"><i class="fa fa-info-circle"></i> Capturing Active Schedule:</strong><br>
              Saving your <strong><?php echo $totalWeeklyExercises; ?> planned exercises</strong> across <strong><?php echo $activeWorkoutDaysCount; ?> training days</strong> as a reusable split preset.
            </div>
            <div class="ios-form-group">
              <label class="ios-label">Preset Title <span style="color: #f87171;">*</span></label>
              <input type="text" name="preset_title" class="ios-input" placeholder="e.g. My 4-Day Strength &amp; Shred Split" required>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Tag / Category</label>
                  <input type="text" name="preset_tag" class="ios-input" placeholder="e.g. 4-Day Hypertrophy" value="Custom Split">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="ios-form-group">
                  <label class="ios-label">Difficulty Level</label>
                  <select name="preset_difficulty" class="ios-input">
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate" selected>Intermediate</option>
                    <option value="Advanced">Advanced</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="ios-form-group">
              <label class="ios-label">Description (Optional)</label>
              <textarea name="preset_description" class="ios-input" rows="2" placeholder="Brief notes on goals, rest days, or tempo..."></textarea>
            </div>
          </div>
          <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal" onclick="closeCustomModal('saveCurrentAsPresetModal')" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 8px 20px; font-size: 13px; font-weight: 700;">
              <i class="fa fa-floppy-o"></i> Save as Preset
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Create & Edit Custom Split Preset Builder Modal -->
  <div class="modal fade" id="customPresetBuilderModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document" style="max-width: 1100px;">
      <div class="modal-content ios-modal">
        <form method="post" id="customPresetBuilderForm" onsubmit="return handlePresetBuilderSubmit(event)">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="action" id="builderAction" value="create_custom_preset">
          <input type="hidden" name="preset_id" id="builderPresetId" value="0">
          <input type="hidden" name="preset_exercises_json" id="builderExercisesJson" value="[]">

          <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 18px 24px;">
            <h4 class="modal-title" id="builderModalTitle" style="font-weight: 800; font-size: 18px; color: #fff; margin: 0;">
              <i class="fa fa-plus-circle" style="color: var(--color-primary); margin-right: 8px;"></i> Create New Split Preset
            </h4>
            <button type="button" class="close" data-dismiss="modal" onclick="closeCustomModal('customPresetBuilderModal')" style="color: #fff; opacity: 0.8; font-size: 24px; border: none; background: transparent; cursor: pointer;">&times;</button>
          </div>
          <div class="modal-body" style="padding: 24px; max-height: calc(85vh - 120px); overflow-y: auto;">
            <!-- Metadata Fields -->
            <div class="row">
              <div class="col-md-5">
                <div class="ios-form-group">
                  <label class="ios-label">Split Preset Title <span style="color: #f87171;">*</span></label>
                  <input type="text" name="preset_title" id="builderPresetTitle" class="ios-input" placeholder="e.g. Upper / Lower Hypertrophy Focus" required>
                </div>
              </div>
              <div class="col-sm-6 col-md-4">
                <div class="ios-form-group">
                  <label class="ios-label">Tag / Focus</label>
                  <input type="text" name="preset_tag" id="builderPresetTag" class="ios-input" placeholder="e.g. 4-Day Strength, Power, PPL" value="Custom Split">
                </div>
              </div>
              <div class="col-sm-6 col-md-3">
                <div class="ios-form-group">
                  <label class="ios-label">Difficulty</label>
                  <select name="preset_difficulty" id="builderPresetDifficulty" class="ios-input">
                    <option value="Beginner">Beginner</option>
                    <option value="Intermediate" selected>Intermediate</option>
                    <option value="Advanced">Advanced</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="ios-form-group">
              <label class="ios-label">Program Overview / Notes (Optional)</label>
              <textarea name="preset_description" id="builderPresetDesc" class="ios-input" rows="2" placeholder="Describe the goal of this program, recovery recommendations, or progression model..."></textarea>
            </div>

            <!-- Interactive Movement Rows Builder -->
            <div style="margin-top: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <div>
                  <h6 style="color: #fff; font-size: 14.5px; font-weight: 700; margin: 0;">
                    <i class="fa fa-list-ol" style="color: #38bdf8; margin-right: 6px;"></i> Program Exercises &amp; Schedule
                  </h6>
                  <span style="font-size: 11.5px; color: #94a3b8;">Add exercises across training days. Use autocomplete from the 386-movement gym library.</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-info" onclick="addPresetBuilderRow()" style="border-radius: 8px; font-size: 12px; padding: 6px 14px; cursor: pointer;">
                  <i class="fa fa-plus"></i> Add Exercise Row
                </button>
              </div>

              <div class="table-responsive" style="border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; background: rgba(0,0,0,0.2);">
                <table class="table table-dark table-sm mb-0" id="builderExercisesTable" style="background: transparent; font-size: 12.5px;">
                  <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.12); color: #94a3b8; font-size: 11px; text-transform: uppercase;">
                      <th style="width: 14%; padding: 10px;">Day of Week</th>
                      <th style="width: 16%; padding: 10px;">Target Muscle</th>
                      <th style="width: 28%; padding: 10px;">Exercise Name</th>
                      <th style="width: 10%; padding: 10px;">Sets</th>
                      <th style="width: 12%; padding: 10px;">Target Reps</th>
                      <th style="width: 15%; padding: 10px;">Form Cue / Notes</th>
                      <th style="width: 5%; text-align: center; padding: 10px;">Action</th>
                    </tr>
                  </thead>
                  <tbody id="builderExercisesTbody">
                    <!-- Populated dynamically via JavaScript -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-outline-light" data-dismiss="modal" onclick="closeCustomModal('customPresetBuilderModal')" style="border-radius: 8px; color: #94a3b8; border-color: rgba(255,255,255,0.15); cursor: pointer;">Cancel</button>
            <button type="submit" class="ios-btn ios-btn-primary" style="padding: 8px 24px; font-size: 13px; font-weight: 700;">
              <i class="fa fa-check"></i> Save Split Preset
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="js/vendor/jquery-3.2.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Tab Switching Functionality with URL Hash persistence & optional scroll
    function switchProfileTab(tabId, el, scrollToTabs) {
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
      
      try {
        if (window.history && window.history.replaceState) {
          window.history.replaceState(null, null, '#' + tabId.replace('tab-', ''));
        }
      } catch(e) {}

      if (scrollToTabs) {
        const tabsBar = document.getElementById('profileTabs');
        if (tabsBar) {
          tabsBar.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }

    // Auto-select tab based on URL hash
    document.addEventListener('DOMContentLoaded', function() {
      const hash = window.location.hash.replace('#', '');
      if (hash && document.getElementById('tab-' + hash)) {
        switchProfileTab('tab-' + hash);
      }
    });

    // Safe Modal Helpers (supports both Bootstrap 4 jQuery and native DOM fallback)
    function showModalSafe(modalId) {
      const modalEl = document.getElementById(modalId);
      if (!modalEl) return;

      if (window.jQuery && typeof jQuery.fn.modal === 'function') {
        try {
          $('#' + modalId).modal('show');
        } catch (err) {
          console.warn('Bootstrap modal show threw, using DOM fallback:', err);
        }
      }

      // Ensure immediate display & visibility
      modalEl.style.display = 'block';
      modalEl.classList.add('show');
      modalEl.removeAttribute('aria-hidden');
      modalEl.setAttribute('aria-modal', 'true');
      document.body.classList.add('modal-open');

      if (!document.getElementById('custom-modal-backdrop') && document.querySelectorAll('.modal-backdrop').length === 0) {
        const backdrop = document.createElement('div');
        backdrop.id = 'custom-modal-backdrop';
        backdrop.className = 'modal-backdrop fade show';
        backdrop.addEventListener('click', function() {
          hideModalSafe(modalId);
        });
        document.body.appendChild(backdrop);
      }
    }

    function hideModalSafe(modalId) {
      const modalEl = document.getElementById(modalId);
      if (modalEl) {
        if (window.jQuery && typeof jQuery.fn.modal === 'function') {
          try {
            $('#' + modalId).modal('hide');
          } catch(err) {}
        }
        modalEl.style.display = 'none';
        modalEl.classList.remove('show');
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
      }

      document.body.classList.remove('modal-open');
      const customBd = document.getElementById('custom-modal-backdrop');
      if (customBd) customBd.remove();
      document.querySelectorAll('.modal-backdrop').forEach(function(b) {
        b.remove();
      });
    }

    function openCustomModal(modalId) { showModalSafe(modalId); }
    function closeCustomModal(modalId) { hideModalSafe(modalId); }

    // Master Exercise Catalog client-side database (380+ exercises)
    const masterExerciseCatalog = <?php echo json_encode($masterExercises, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Filter Modal Exercise Dropdown Contextually by Selected Muscle Group
    function filterModalExercisesByMuscle(selectedMuscle, targetSelectId = 'catalogQuickSelect', countBadgeId = 'catalogQuickCount', customPreselectedEx = null) {
      const select = document.getElementById(targetSelectId);
      const countBadge = document.getElementById(countBadgeId);
      if (!select || !masterExerciseCatalog) return;

      select.innerHTML = '';
      const rawTarget = (selectedMuscle || 'all').trim();
      const normTarget = rawTarget.toLowerCase();

      const filtered = masterExerciseCatalog.filter(function(ex) {
        if (!rawTarget || rawTarget === 'all' || normTarget === 'all') return true;
        const mg = ex.muscle_group || '';
        const mgLower = mg.toLowerCase();

        // Exact match
        if (mgLower === normTarget || mg === rawTarget) return true;

        // Strict category segregation
        if (normTarget === 'chest') return mgLower === 'chest';
        if (normTarget === 'back') return mgLower === 'back';
        if (normTarget === 'legs' || normTarget === 'leg') return mgLower === 'legs';
        if (normTarget === 'shoulders' || normTarget === 'shoulder') return mgLower === 'shoulders';
        if (normTarget === 'biceps' || normTarget === 'bicep') return mgLower === 'biceps';
        if (normTarget === 'triceps' || normTarget === 'tricep') return mgLower === 'triceps';
        if (normTarget.includes('abs') || normTarget.includes('core')) return mgLower === 'abs & core';
        if (normTarget.includes('cardio')) return mgLower === 'cardio';
        if (normTarget.includes('functional') || normTarget.includes('mobility')) return mgLower === 'functional & mobility';

        // Multi-muscle split support (e.g. "Chest & Triceps")
        if (normTarget.includes('&') || normTarget.includes('/')) {
          const parts = normTarget.split(/[&/+,]/).map(s => s.trim());
          for (const p of parts) {
            if (p && mgLower.includes(p)) return true;
          }
        }

        return mgLower.includes(normTarget);
      });

      // Default placeholder option
      const defaultOpt = document.createElement('option');
      defaultOpt.value = '';
      const displayTitle = (selectedMuscle === 'all' || !selectedMuscle) ? 'All Categories' : selectedMuscle + ' Exercises';
      defaultOpt.textContent = '-- Select from ' + filtered.length + ' ' + displayTitle + ' --';
      select.appendChild(defaultOpt);

      // Group exercises by category (Barbell, Dumbbells, Machines, Floor, etc.)
      const groups = {};
      filtered.forEach(function(ex) {
        const cat = ex.category || 'General Exercises';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(ex);
      });

      for (const catName in groups) {
        const optgroup = document.createElement('optgroup');
        optgroup.label = catName + ' (' + groups[catName].length + ')';
        groups[catName].forEach(function(ex) {
          const opt = document.createElement('option');
          opt.value = JSON.stringify(ex);
          opt.textContent = ex.name + ' • ' + ex.target_muscle + ' (' + ex.recommended_sets + 's × ' + ex.recommended_reps + ')';
          if (customPreselectedEx && ex.name === customPreselectedEx) {
            opt.selected = true;
          }
          optgroup.appendChild(opt);
        });
        select.appendChild(optgroup);
      }

      if (countBadge) {
        countBadge.textContent = filtered.length + ' ' + (selectedMuscle === 'all' ? 'Total' : selectedMuscle) + ' Available';
      }

      // Update autocomplete datalist
      const datalist = document.getElementById('exerciseSuggestions');
      if (datalist && targetSelectId === 'catalogQuickSelect') {
        datalist.innerHTML = '';
        filtered.forEach(function(ex) {
          const dOpt = document.createElement('option');
          dOpt.value = ex.name;
          dOpt.label = ex.target_muscle + ' (' + ex.equipment + ')';
          datalist.appendChild(dOpt);
        });
      }
    }

    // Routine Planner Modal & Filter Functions
    function openAddWorkoutModal(day, suggestedMuscle) {
      if (day) {
        const d = document.getElementById('addWorkoutDay');
        if (d) d.value = day;
      }

      const mSelect = document.getElementById('addWorkoutMuscle');
      let targetM = suggestedMuscle || (mSelect ? mSelect.value : 'Chest');
      if (!targetM) targetM = 'Chest';

      if (mSelect) {
        let matched = false;
        for (let i = 0; i < mSelect.options.length; i++) {
          if (mSelect.options[i].value.toLowerCase() === targetM.toLowerCase() ||
              targetM.toLowerCase().includes(mSelect.options[i].value.toLowerCase())) {
            mSelect.selectedIndex = i;
            matched = true;
            break;
          }
        }
        if (!matched) {
          mSelect.value = 'Chest';
        }
        filterModalExercisesByMuscle(mSelect.value, 'catalogQuickSelect', 'catalogQuickCount');
      }

      showModalSafe('addWorkoutModal');
    }

    function openEditWorkoutModal(id, day, muscle, name, sets, reps, notes) {
      document.getElementById('editWorkoutId').value = id || '';
      document.getElementById('editWorkoutDay').value = day || 'Monday';
      document.getElementById('editWorkoutName').value = name || '';
      document.getElementById('editWorkoutSets').value = sets || 3;
      document.getElementById('editWorkoutReps').value = reps || '10-12 reps';
      document.getElementById('editWorkoutNotes').value = notes || '';

      const mSelect = document.getElementById('editWorkoutMuscle');
      let targetM = muscle || 'Chest';
      if (mSelect) {
        let matched = false;
        for (let i = 0; i < mSelect.options.length; i++) {
          if (mSelect.options[i].value.toLowerCase() === targetM.toLowerCase() ||
              targetM.toLowerCase().includes(mSelect.options[i].value.toLowerCase())) {
            mSelect.selectedIndex = i;
            matched = true;
            break;
          }
        }
        if (!matched) {
          mSelect.value = 'Chest';
        }
        filterModalExercisesByMuscle(mSelect.value, 'editCatalogQuickSelect', 'editCatalogQuickCount', name);
      }

      showModalSafe('editWorkoutModal');
    }

    // Master System & User Presets Databases
    const systemWorkoutPresets = <?php echo json_encode($systemWorkoutPresets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const userWorkoutPresets = <?php echo json_encode($userWorkoutPresets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    let activePresetCategory = 'all';

    function openPresetSplitsModal() {
      showModalSafe('presetSplitsModal');
    }

    function openSaveCurrentAsPresetModal() {
      hideModalSafe('presetSplitsModal');
      showModalSafe('saveCurrentAsPresetModal');
    }

    function setPresetFilter(cat, btn) {
      activePresetCategory = cat;
      document.querySelectorAll('.preset-filter-chip').forEach(function(c) {
        c.classList.remove('active');
        c.style.background = 'rgba(255,255,255,0.04)';
        c.style.borderColor = 'rgba(255,255,255,0.1)';
        c.style.color = '#cbd5e1';
      });
      if (btn) {
        btn.classList.add('active');
        btn.style.background = 'rgba(56,189,248,0.18)';
        btn.style.borderColor = 'rgba(56,189,248,0.4)';
        btn.style.color = '#38bdf8';
      }
      filterPresetCards();
    }

    function filterPresetCards() {
      const q = (document.getElementById('presetSearchInput').value || '').toLowerCase().trim();
      document.querySelectorAll('#presetsCardsGrid .preset-item').forEach(function(item) {
        const type = item.getAttribute('data-type');
        const text = (item.getAttribute('data-search') || '').toLowerCase();
        let matchesType = (activePresetCategory === 'all') || (type === activePresetCategory);
        let matchesQuery = !q || text.includes(q);
        item.style.display = (matchesType && matchesQuery) ? '' : 'none';
      });
    }

    function previewPreset(identifier) {
      let preset = null;
      if (typeof identifier === 'number' || (!isNaN(Number(identifier)) && String(identifier).trim() !== '')) {
        preset = userWorkoutPresets.find(function(p) { return String(p.id) === String(identifier); });
      } else {
        preset = systemWorkoutPresets[identifier];
      }
      if (!preset) return;

      document.getElementById('previewPresetTitle').textContent = preset.title || 'Workout Split';
      const badge = document.getElementById('previewPresetBadge');
      badge.textContent = preset.tag || (preset.is_system ? 'Verified Program' : 'Custom Split');
      badge.style.background = preset.badge_bg || 'rgba(56,189,248,0.15)';
      badge.style.color = preset.badge_color || '#38bdf8';

      document.getElementById('previewPresetDesc').textContent = preset.description || 'Structured workout split designed for continuous athletic progress.';
      document.getElementById('previewApplyType').value = identifier;

      const exercises = preset.exercises || [];
      const daysCount = preset.days_count || (new Set(exercises.map(function(e) { return e.day; }))).size;
      const diff = preset.difficulty || 'Intermediate';

      document.getElementById('previewPresetStats').innerHTML = 
        '<span style="font-size: 11.5px; background: rgba(56,189,248,0.1); color: #38bdf8; padding: 4px 10px; border-radius: 8px; font-weight: 600;"><i class="fa fa-calendar"></i> ' + daysCount + ' Training Days</span>' +
        '<span style="font-size: 11.5px; background: rgba(251,191,36,0.1); color: #fbbf24; padding: 4px 10px; border-radius: 8px; font-weight: 600;"><i class="fa fa-list"></i> ' + exercises.length + ' Total Movements</span>' +
        '<span style="font-size: 11.5px; background: rgba(255,255,255,0.06); color: #cbd5e1; padding: 4px 10px; border-radius: 8px;"><i class="fa fa-tachometer"></i> ' + diff + '</span>';

      // Group exercises by day
      const daysOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      const byDay = {};
      exercises.forEach(function(ex) {
        const d = ex.day || 'Monday';
        if (!byDay[d]) byDay[d] = [];
        byDay[d].push(ex);
      });

      let daysHtml = '';
      daysOrder.forEach(function(day) {
        if (!byDay[day] || byDay[day].length === 0) return;
        daysHtml += '<div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; margin-bottom: 14px; overflow: hidden;">' +
          '<div style="background: rgba(255,255,255,0.05); padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06);">' +
            '<strong style="color: #fff; font-size: 13.5px;"><i class="fa fa-calendar-o" style="color: #38bdf8; margin-right: 6px;"></i> ' + day + '</strong>' +
            '<span style="font-size: 11.5px; color: #94a3b8;">' + byDay[day].length + ' exercises</span>' +
          '</div>' +
          '<div style="padding: 10px 16px;">';

        byDay[day].forEach(function(ex, idx) {
          daysHtml += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 7px 0;' + (idx < byDay[day].length - 1 ? ' border-bottom: 1px solid rgba(255,255,255,0.04);' : '') + '">' +
            '<div>' +
              '<div style="color: #fff; font-weight: 600; font-size: 13px;">' + (ex.name || 'Exercise') + '</div>' +
              '<small style="color: #94a3b8; font-size: 11px;">' + (ex.muscle ? ex.muscle + ' &bull; ' : '') + (ex.notes || '') + '</small>' +
            '</div>' +
            '<div style="text-align: right;">' +
              '<span style="font-size: 12px; color: #38bdf8; font-weight: 700;">' + (ex.sets || 3) + ' sets</span>' +
              '<span style="font-size: 11.5px; color: #cbd5e1; margin-left: 6px;">(' + (ex.reps || '10-12 reps') + ')</span>' +
            '</div>' +
          '</div>';
        });

        daysHtml += '</div></div>';
      });

      document.getElementById('previewPresetDaysContainer').innerHTML = daysHtml || '<p style="color: #94a3b8;">No exercises scheduled for this split.</p>';

      hideModalSafe('presetSplitsModal');
      showModalSafe('previewPresetModal');
    }

    function openCreatePresetModal() {
      document.getElementById('builderAction').value = 'create_custom_preset';
      document.getElementById('builderPresetId').value = '0';
      document.getElementById('builderModalTitle').innerHTML = '<i class="fa fa-plus-circle" style="color: var(--color-primary); margin-right: 8px;"></i> Create New Split Preset';
      document.getElementById('builderPresetTitle').value = '';
      document.getElementById('builderPresetTag').value = 'Custom Split';
      document.getElementById('builderPresetDifficulty').value = 'Intermediate';
      document.getElementById('builderPresetDesc').value = '';

      const tbody = document.getElementById('builderExercisesTbody');
      tbody.innerHTML = '';
      // Seed 3 initial rows for smooth user start
      addPresetBuilderRow('Monday', 'Chest', '', 4, '8-10 reps', '');
      addPresetBuilderRow('Wednesday', 'Back', '', 4, '8-10 reps', '');
      addPresetBuilderRow('Friday', 'Legs', '', 4, '8-10 reps', '');

      hideModalSafe('presetSplitsModal');
      showModalSafe('customPresetBuilderModal');
    }

    function openEditPresetModal(presetId) {
      const preset = userWorkoutPresets.find(function(p) { return String(p.id) === String(presetId); });
      if (!preset) return;

      document.getElementById('builderAction').value = 'edit_custom_preset';
      document.getElementById('builderPresetId').value = preset.id;
      document.getElementById('builderModalTitle').innerHTML = '<i class="fa fa-pencil" style="color: #fbbf24; margin-right: 8px;"></i> Edit Custom Split: ' + (preset.title || '');
      document.getElementById('builderPresetTitle').value = preset.title || '';
      document.getElementById('builderPresetTag').value = preset.tag || 'Custom Split';
      document.getElementById('builderPresetDifficulty').value = preset.difficulty || 'Intermediate';
      document.getElementById('builderPresetDesc').value = preset.description || '';

      const tbody = document.getElementById('builderExercisesTbody');
      tbody.innerHTML = '';
      const exs = preset.exercises || [];
      if (exs.length === 0) {
        addPresetBuilderRow('Monday', 'Chest', '', 3, '10-12 reps', '');
      } else {
        exs.forEach(function(ex) {
          addPresetBuilderRow(ex.day || 'Monday', ex.muscle || 'Chest', ex.name || '', ex.sets || 3, ex.reps || '10-12 reps', ex.notes || '');
        });
      }

      hideModalSafe('presetSplitsModal');
      showModalSafe('customPresetBuilderModal');
    }

    function addPresetBuilderRow(day = 'Monday', muscle = 'Chest', name = '', sets = 3, reps = '10-12 reps', notes = '') {
      const tbody = document.getElementById('builderExercisesTbody');
      const tr = document.createElement('tr');
      tr.className = 'builder-exercise-row';
      tr.style.borderBottom = '1px solid rgba(255,255,255,0.06)';

      tr.innerHTML = 
        '<td data-label="Day of Week" style="padding: 6px;">' +
          '<select class="ios-input row-day" style="font-size: 12px; padding: 6px 8px;">' +
            '<option value="Monday"' + (day === 'Monday' ? ' selected' : '') + '>Monday</option>' +
            '<option value="Tuesday"' + (day === 'Tuesday' ? ' selected' : '') + '>Tuesday</option>' +
            '<option value="Wednesday"' + (day === 'Wednesday' ? ' selected' : '') + '>Wednesday</option>' +
            '<option value="Thursday"' + (day === 'Thursday' ? ' selected' : '') + '>Thursday</option>' +
            '<option value="Friday"' + (day === 'Friday' ? ' selected' : '') + '>Friday</option>' +
            '<option value="Saturday"' + (day === 'Saturday' ? ' selected' : '') + '>Saturday</option>' +
            '<option value="Sunday"' + (day === 'Sunday' ? ' selected' : '') + '>Sunday</option>' +
          '</select>' +
        '</td>' +
        '<td data-label="Target Muscle" style="padding: 6px;">' +
          '<select class="ios-input row-muscle" style="font-size: 12px; padding: 6px 8px;">' +
            '<option value="Chest"' + (muscle === 'Chest' ? ' selected' : '') + '>Chest</option>' +
            '<option value="Back"' + (muscle === 'Back' ? ' selected' : '') + '>Back</option>' +
            '<option value="Legs"' + (muscle === 'Legs' ? ' selected' : '') + '>Legs</option>' +
            '<option value="Shoulders"' + (muscle === 'Shoulders' ? ' selected' : '') + '>Shoulders</option>' +
            '<option value="Biceps"' + (muscle === 'Biceps' ? ' selected' : '') + '>Biceps</option>' +
            '<option value="Triceps"' + (muscle === 'Triceps' ? ' selected' : '') + '>Triceps</option>' +
            '<option value="Abs & Core"' + (muscle.includes('Abs') || muscle.includes('Core') ? ' selected' : '') + '>Abs & Core</option>' +
            '<option value="Cardio"' + (muscle === 'Cardio' ? ' selected' : '') + '>Cardio</option>' +
            '<option value="Functional & Mobility"' + (muscle.includes('Functional') ? ' selected' : '') + '>Functional</option>' +
          '</select>' +
        '</td>' +
        '<td data-label="Exercise Movement" style="padding: 6px;">' +
          '<input type="text" class="ios-input row-name" list="exerciseSuggestions" value="' + (name.replace(/"/g, '&quot;')) + '" placeholder="Type or select exercise..." style="font-size: 12px; padding: 6px 10px;" required>' +
        '</td>' +
        '<td data-label="Sets" style="padding: 6px;">' +
          '<input type="number" class="ios-input row-sets" value="' + sets + '" min="1" max="10" style="font-size: 12px; padding: 6px 8px;" required>' +
        '</td>' +
        '<td data-label="Target Reps" style="padding: 6px;">' +
          '<input type="text" class="ios-input row-reps" value="' + (reps.replace(/"/g, '&quot;')) + '" placeholder="e.g. 8-10 reps" style="font-size: 12px; padding: 6px 8px;" required>' +
        '</td>' +
        '<td data-label="Form Cue / Notes" style="padding: 6px;">' +
          '<input type="text" class="ios-input row-notes" value="' + (notes.replace(/"/g, '&quot;')) + '" placeholder="Cues, cadence..." style="font-size: 12px; padding: 6px 8px;">' +
        '</td>' +
        '<td data-label="Remove" style="padding: 6px; text-align: center;">' +
          '<button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest(\'tr\').remove()" style="padding: 6px 10px; border-radius: 6px;"><i class="fa fa-times"></i> <span class="d-md-none" style="margin-left: 4px;">Remove Movement</span></button>' +
        '</td>';

      tbody.appendChild(tr);
    }

    function handlePresetBuilderSubmit(e) {
      const rows = [];
      const trs = document.querySelectorAll('#builderExercisesTbody tr');
      trs.forEach(function(tr) {
        const day = (tr.querySelector('.row-day') || {}).value || 'Monday';
        const muscle = (tr.querySelector('.row-muscle') || {}).value || 'Chest';
        const name = ((tr.querySelector('.row-name') || {}).value || '').trim();
        const sets = parseInt((tr.querySelector('.row-sets') || {}).value || 3, 10);
        const reps = ((tr.querySelector('.row-reps') || {}).value || '10-12 reps').trim();
        const notes = ((tr.querySelector('.row-notes') || {}).value || '').trim();

        if (name !== '') {
          rows.push({ day: day, muscle: muscle, name: name, sets: sets, reps: reps, notes: notes });
        }
      });

      if (rows.length === 0) {
        alert('Please add at least one exercise movement to your split preset.');
        if (e) e.preventDefault();
        return false;
      }

      const jsonField = document.getElementById('builderExercisesJson');
      if (jsonField) {
        jsonField.value = JSON.stringify(rows);
      }
      return true;
    }

    function scrollToExerciseCatalog() {
      switchProfileTab('tab-routine');
      setTimeout(function() {
        const el = document.getElementById('exerciseCatalogSection');
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }, 50);
    }

    // Quick Add from Master Exercise Catalog to Routine Modal
    function quickAddFromCatalog(name, muscle, sets, reps, formCue) {
      document.getElementById('addWorkoutName').value = name || '';
      document.getElementById('addWorkoutSets').value = sets || 3;
      document.getElementById('addWorkoutReps').value = reps || '10-12 reps';
      document.getElementById('addWorkoutNotes').value = formCue || '';

      const mSelect = document.getElementById('addWorkoutMuscle');
      if (mSelect && muscle) {
        for (let i = 0; i < mSelect.options.length; i++) {
          if (mSelect.options[i].value.toLowerCase() === muscle.toLowerCase() ||
              muscle.toLowerCase().includes(mSelect.options[i].value.toLowerCase())) {
            mSelect.selectedIndex = i;
            break;
          }
        }
        filterModalExercisesByMuscle(mSelect.value, 'catalogQuickSelect', 'catalogQuickCount', name);
      } else if (mSelect) {
        filterModalExercisesByMuscle(mSelect.value, 'catalogQuickSelect', 'catalogQuickCount', name);
      }

      showModalSafe('addWorkoutModal');
    }

    // Auto-fill from catalog dropdown in Add Modal
    function applyCatalogSelection(rawJson) {
      if (!rawJson) return;
      try {
        const ex = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
        document.getElementById('addWorkoutName').value = ex.name || '';
        document.getElementById('addWorkoutSets').value = ex.recommended_sets || 3;
        document.getElementById('addWorkoutReps').value = ex.recommended_reps || '10-12 reps';
        document.getElementById('addWorkoutNotes').value = ex.form_cue || '';
      } catch (e) {
        console.error('Error parsing catalog exercise:', e);
      }
    }

    // Auto-fill from catalog dropdown in Edit Modal
    function applyEditCatalogSelection(rawJson) {
      if (!rawJson) return;
      try {
        const ex = typeof rawJson === 'string' ? JSON.parse(rawJson) : rawJson;
        document.getElementById('editWorkoutName').value = ex.name || '';
        document.getElementById('editWorkoutSets').value = ex.recommended_sets || 3;
        document.getElementById('editWorkoutReps').value = ex.recommended_reps || '10-12 reps';
        document.getElementById('editWorkoutNotes').value = ex.form_cue || '';
      } catch (e) {
        console.error('Error parsing catalog exercise in edit:', e);
      }
    }

    // Global backdrop, dismiss button, and Escape key listeners
    document.addEventListener('click', function(e) {
      const dismissBtn = e.target.closest('[data-dismiss="modal"]');
      if (dismissBtn) {
        const parentModal = dismissBtn.closest('.modal');
        if (parentModal) {
          hideModalSafe(parentModal.id);
        }
      }
      if (e.target.classList.contains('modal') && (e.target.classList.contains('show') || e.target.style.display === 'block')) {
        hideModalSafe(e.target.id);
      }
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(function(m) {
          if (m.classList.contains('show') || m.style.display === 'block') {
            hideModalSafe(m.id);
          }
        });
      }
    });

    // Auto-fill from catalog dropdown in Add Modal
    function applyCatalogSelection(rawJson) {
      if (!rawJson) return;
      try {
        const ex = JSON.parse(rawJson);
        document.getElementById('addWorkoutName').value = ex.name || '';
        document.getElementById('addWorkoutMuscle').value = ex.target_muscle || '';
        document.getElementById('addWorkoutSets').value = ex.recommended_sets || 3;
        document.getElementById('addWorkoutReps').value = ex.recommended_reps || '10-12 reps';
        document.getElementById('addWorkoutNotes').value = ex.form_cue || '';
      } catch (e) {
        console.error('Error parsing catalog exercise:', e);
      }
    }

    // Filter Weekly Routine Days (Monday - Sunday)
    function filterRoutineDays(day, btn) {
      document.querySelectorAll('.routine-filter-chip').forEach(function(b) {
        b.classList.remove('active');
      });
      if (btn) btn.classList.add('active');

      const allCards = document.querySelectorAll('.routine-day-card');
      allCards.forEach(function(card) {
        if (day === 'all' || card.getAttribute('data-day') === day) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Filter Master Exercise Catalog by Search, Category & Muscle
    let currentCatalogCategory = 'all';

    function setCatalogCategory(catKey, btn) {
      currentCatalogCategory = catKey;
      document.querySelectorAll('.catalog-filter-btn').forEach(function(b) {
        b.classList.remove('active');
      });
      if (btn) btn.classList.add('active');
      filterCatalogExercises();
    }

    function filterCatalogExercises() {
      const search = (document.getElementById('catalogSearchInput')?.value || '').toLowerCase().trim();
      const muscle = document.getElementById('catalogMuscleFilter')?.value || 'all';
      const items = document.querySelectorAll('.catalog-item');
      let visibleCount = 0;

      items.forEach(function(item) {
        const itemCat = item.getAttribute('data-category');
        const itemMuscle = item.getAttribute('data-muscle');
        const itemName = item.getAttribute('data-name');
        const itemEquip = item.getAttribute('data-equipment');

        const matchesCat = (currentCatalogCategory === 'all' || itemCat === currentCatalogCategory);
        const matchesMuscle = (muscle === 'all' || itemMuscle === muscle);
        const matchesSearch = (!search || itemName.includes(search) || itemEquip.includes(search) || itemMuscle.toLowerCase().includes(search));

        if (matchesCat && matchesMuscle && matchesSearch) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      const badge = document.getElementById('catalogCountBadge');
      if (badge) {
        badge.textContent = visibleCount + ' Exercises Available';
      }

      const emptyMsg = document.getElementById('catalogEmptyMessage');
      if (emptyMsg) {
        emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
      }
    }

    function sortCatalogExercises() {
      const order = document.getElementById('catalogSortOrder')?.value || 'category';
      const container = document.getElementById('catalogCardsContainer');
      if (!container) return;
      const items = Array.from(container.querySelectorAll('.catalog-item'));

      items.sort(function(a, b) {
        if (order === 'name_asc') {
          return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
        } else if (order === 'muscle') {
          return a.getAttribute('data-muscle').localeCompare(b.getAttribute('data-muscle'));
        } else if (order === 'difficulty') {
          const diffRank = { 'Beginner': 1, 'Intermediate': 2, 'Advanced': 3 };
          return (diffRank[a.getAttribute('data-difficulty')] || 2) - (diffRank[b.getAttribute('data-difficulty')] || 2);
        } else {
          // Default: Category order
          const catRank = { 'floor': 1, 'cardio': 2, 'dumbbells': 3, 'barbell': 4, 'machines': 5, 'functional': 6 };
          return (catRank[a.getAttribute('data-category')] || 99) - (catRank[b.getAttribute('data-category')] || 99);
        }
      });

      items.forEach(function(item) {
        container.appendChild(item);
      });
    }

    function resetCatalogFilters() {
      document.getElementById('catalogSearchInput').value = '';
      document.getElementById('catalogMuscleFilter').value = 'all';
      document.getElementById('catalogSortOrder').value = 'category';
      setCatalogCategory('all', document.querySelector('.catalog-filter-btn[data-cat="all"]'));
    }

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
