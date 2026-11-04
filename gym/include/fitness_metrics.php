<?php
/**
 * Fitness Metrics & Nutrition Engine
 * FitForge / Golden Eagle Gym Management System
 * 
 * Provides pure, reproducible mathematical models for:
 * - BMI & Pediatric Range Evaluation
 * - BMR (Mifflin–St Jeor Equation)
 * - TDEE & Activity Multipliers
 * - Goal-based Caloric Targets & Safety Floors
 * - Macronutrient & Hydration Guidelines
 * - Body Proportional Indicators (WHtR, WHR)
 * - Imperial <-> Metric Conversions
 */

if (!defined('METRIC_KG_TO_LB')) define('METRIC_KG_TO_LB', 2.20462262);
if (!defined('METRIC_CM_TO_INCH')) define('METRIC_CM_TO_INCH', 0.393700787);

// ==============================================================================
// 1. UNIT CONVERSION HELPERS
// ==============================================================================

function fm_kg_to_lb($kg) {
    return floatval($kg) * METRIC_KG_TO_LB;
}

function fm_lb_to_kg($lb) {
    return floatval($lb) / METRIC_KG_TO_LB;
}

function fm_cm_to_in($cm) {
    return floatval($cm) * METRIC_CM_TO_INCH;
}

function fm_in_to_cm($in) {
    return floatval($in) / METRIC_CM_TO_INCH;
}

function fm_cm_to_ft_in($cm) {
    $totalInches = fm_cm_to_in($cm);
    $feet = floor($totalInches / 12);
    $inches = round($totalInches - ($feet * 12), 1);
    return ['feet' => intval($feet), 'inches' => floatval($inches)];
}

function fm_ft_in_to_cm($feet, $inches) {
    $totalInches = (floatval($feet) * 12) + floatval($inches);
    return round(fm_in_to_cm($totalInches), 2);
}

// ==============================================================================
// 2. AGE CALCULATION
// ==============================================================================

function fm_calculate_age($dob, $reference_date = null) {
    if (empty($dob) || $dob === '0000-00-00') {
        return null;
    }
    try {
        $birthDate = new DateTime($dob);
        $refDate = $reference_date ? new DateTime($reference_date) : new DateTime('today');
        if ($birthDate > $refDate) {
            return null;
        }
        return $birthDate->diff($refDate)->y;
    } catch (Exception $e) {
        return null;
    }
}

// ==============================================================================
// 3. BMI (BODY MASS INDEX)
// ==============================================================================

function fm_calculate_bmi($weight_kg, $height_cm, $age = null) {
    $w = floatval($weight_kg);
    $h = floatval($height_cm);
    if ($w <= 0 || $h <= 0) {
        return [
            'status' => 'missing_data',
            'bmi' => null,
            'category' => 'Incomplete data',
            'badge_class' => 'ios-badge-secondary',
            'message' => 'Height and weight are required to calculate BMI.'
        ];
    }

    $heightMeters = $h / 100.0;
    $bmi = round($w / ($heightMeters * $heightMeters), 1);
    $isMinor = ($age !== null && $age < 18);

    if ($isMinor) {
        return [
            'status' => 'pediatric',
            'bmi' => $bmi,
            'is_minor' => true,
            'category' => 'Pediatric Range (<18y)',
            'badge_class' => 'ios-badge-warning',
            'message' => 'Standard adult BMI categories do not apply to minors under 18. Growth charts and percentiles should be evaluated by a healthcare provider.',
            'healthy_range' => null
        ];
    }

    // Standard WHO Adult Categories
    $category = 'Normal weight';
    $badgeClass = 'ios-badge-success';
    if ($bmi < 18.5) {
        $category = 'Underweight';
        $badgeClass = 'ios-badge-info';
    } elseif ($bmi <= 24.9) {
        $category = 'Normal weight';
        $badgeClass = 'ios-badge-success';
    } elseif ($bmi <= 29.9) {
        $category = 'Overweight';
        $badgeClass = 'ios-badge-warning';
    } elseif ($bmi <= 34.9) {
        $category = 'Obesity Class I';
        $badgeClass = 'ios-badge-danger';
    } elseif ($bmi <= 39.9) {
        $category = 'Obesity Class II';
        $badgeClass = 'ios-badge-danger';
    } else {
        $category = 'Obesity Class III (Severe)';
        $badgeClass = 'ios-badge-danger';
    }

    $minHealthyKg = round(18.5 * ($heightMeters * $heightMeters), 1);
    $maxHealthyKg = round(24.9 * ($heightMeters * $heightMeters), 1);

    return [
        'status' => 'calculated',
        'bmi' => $bmi,
        'is_minor' => false,
        'category' => $category,
        'badge_class' => $badgeClass,
        'healthy_range' => [
            'min_kg' => $minHealthyKg,
            'max_kg' => $maxHealthyKg
        ],
        'explanation' => 'BMI is a statistical screening measure and does not directly differentiate between lean muscle mass and adipose tissue.'
    ];
}

// ==============================================================================
// 4. BMR (BASAL METABOLIC RATE) — MIFFLIN–ST JEOR EQUATION
// ==============================================================================

function fm_calculate_bmr_mifflin($weight_kg, $height_cm, $age, $gender) {
    $w = floatval($weight_kg);
    $h = floatval($height_cm);
    $a = intval($age);

    if ($w <= 0 || $h <= 0 || $a <= 0) {
        return [
            'status' => 'missing_data',
            'bmr' => null,
            'message' => 'Weight, height, and age (DOB) are required to calculate estimated Basal Metabolic Rate.'
        ];
    }

    $base = (10.0 * $w) + (6.25 * $h) - (5.0 * $a);
    $normalizedGender = strtolower(trim((string)$gender));

    if ($normalizedGender === 'male') {
        $bmr = round($base + 5);
        return [
            'status' => 'calculated',
            'bmr' => $bmr,
            'gender_used' => 'male',
            'formula' => 'Mifflin–St Jeor (Male: 10w + 6.25h − 5a + 5)',
            'message' => 'Estimated daily calories burned at complete metabolic rest.'
        ];
    } elseif ($normalizedGender === 'female') {
        $bmr = round($base - 161);
        return [
            'status' => 'calculated',
            'bmr' => $bmr,
            'gender_used' => 'female',
            'formula' => 'Mifflin–St Jeor (Female: 10w + 6.25h − 5a − 161)',
            'message' => 'Estimated daily calories burned at complete metabolic rest.'
        ];
    } else {
        // Unspecified or non-binary sex/gender input
        $bmrFemaleEst = round($base - 161);
        $bmrMaleEst = round($base + 5);
        return [
            'status' => 'unspecified_sex',
            'bmr' => null,
            'estimated_range' => [$bmrFemaleEst, $bmrMaleEst],
            'message' => 'Biological sex input is required for single-point Mifflin–St Jeor equation accuracy. (Estimated resting range: ~' . number_format($bmrFemaleEst) . ' – ' . number_format($bmrMaleEst) . ' kcal/day).'
        ];
    }
}

// ==============================================================================
// 5. TDEE (TOTAL DAILY ENERGY EXPENDITURE)
// ==============================================================================

function fm_calculate_tdee($bmr, $activity_level) {
    if (!$bmr || floatval($bmr) <= 0) {
        return [
            'status' => 'missing_bmr',
            'tdee' => null,
            'multiplier' => null,
            'message' => 'BMR must be calculable before estimating Total Daily Energy Expenditure.'
        ];
    }

    $multipliers = [
        'sedentary' => ['mult' => 1.200, 'label' => 'Sedentary (Desk job, little or no exercise)'],
        'lightly_active' => ['mult' => 1.375, 'label' => 'Lightly Active (Light exercise 1–3 days/week)'],
        'moderately_active' => ['mult' => 1.550, 'label' => 'Moderately Active (Moderate exercise 3–5 days/week)'],
        'very_active' => ['mult' => 1.725, 'label' => 'Very Active (Hard training 6–7 days/week)'],
        'extra_active' => ['mult' => 1.900, 'label' => 'Extra Active (Very heavy training or physical job)']
    ];

    $actKey = strtolower(trim((string)$activity_level));
    $entry = $multipliers[$actKey] ?? $multipliers['moderately_active'];
    $tdee = round(floatval($bmr) * $entry['mult']);

    return [
        'status' => 'calculated',
        'tdee' => $tdee,
        'multiplier' => $entry['mult'],
        'activity_key' => $actKey,
        'activity_label' => $entry['label'],
        'explanation' => 'TDEE estimates total daily calories burned including basal metabolism, digestion, and physical lifestyle activity.'
    ];
}

// ==============================================================================
// 6. DAILY CALORIC TARGET & SAFETY FLOORS
// ==============================================================================

function fm_calculate_calorie_target($tdee, $goal, $gender = null, $age = null) {
    if (!$tdee || floatval($tdee) <= 0) {
        return [
            'status' => 'missing_tdee',
            'target_calories' => null,
            'message' => 'TDEE is required to calculate a personalized caloric target.'
        ];
    }

    // Minor safeguard
    if ($age !== null && intval($age) < 18) {
        return [
            'status' => 'minor_limitation',
            'target_calories' => null,
            'is_minor' => true,
            'message' => 'Caloric restriction targets are not advised for minors under 18 without professional pediatric or sports nutrition guidance.'
        ];
    }

    $g = strtolower(trim((string)$gender));
    $minFloor = ($g === 'female') ? 1200 : (($g === 'male') ? 1500 : 1350);

    $goalKey = strtolower(trim((string)$goal));
    $adjustments = [
        'fat_loss' => ['delta' => -450, 'label' => 'Gradual Fat Loss (~0.4 kg/wk)', 'type' => 'deficit'],
        'muscle_gain' => ['delta' => 350, 'label' => 'Gradual Muscle Gain / Lean Surplus', 'type' => 'surplus'],
        'strength' => ['delta' => 150, 'label' => 'Strength & Power Performance', 'type' => 'slight_surplus'],
        'endurance' => ['delta' => 150, 'label' => 'Endurance & Athletic Recovery', 'type' => 'slight_surplus'],
        'maintenance' => ['delta' => 0, 'label' => 'Weight Maintenance', 'type' => 'neutral'],
        'general_fitness' => ['delta' => 0, 'label' => 'General Fitness & Energy Balance', 'type' => 'neutral']
    ];

    $cfg = $adjustments[$goalKey] ?? $adjustments['general_fitness'];
    $rawTarget = floatval($tdee) + $cfg['delta'];
    $clampedTarget = max($minFloor, round($rawTarget));

    return [
        'status' => 'calculated',
        'target_calories' => $clampedTarget,
        'delta' => $cfg['delta'],
        'goal_key' => $goalKey,
        'goal_label' => $cfg['label'],
        'safety_floor_applied' => ($rawTarget < $minFloor),
        'min_floor' => $minFloor,
        'disclaimer' => 'Calorie targets are responsible estimates based on energy expenditure models, not medical prescriptions.'
    ];
}

// ==============================================================================
// 7. MACRONUTRIENT & HYDRATION GUIDELINES
// ==============================================================================

function fm_calculate_macronutrients($calorie_target, $weight_kg, $goal) {
    if (!$calorie_target || floatval($calorie_target) <= 0 || !$weight_kg || floatval($weight_kg) <= 0) {
        return [
            'status' => 'missing_data',
            'macros' => null
        ];
    }

    $cals = floatval($calorie_target);
    $w = floatval($weight_kg);
    $goalKey = strtolower(trim((string)$goal));

    // Protein per kg bodyweight
    if ($goalKey === 'fat_loss') {
        $proteinGramsPerKg = 2.0; // Higher protein preserves lean mass in deficit
    } elseif ($goalKey === 'muscle_gain' || $goalKey === 'strength') {
        $proteinGramsPerKg = 1.9;
    } elseif ($goalKey === 'endurance') {
        $proteinGramsPerKg = 1.6;
    } else {
        $proteinGramsPerKg = 1.6;
    }

    $proteinGrams = round($w * $proteinGramsPerKg);
    $proteinCals = $proteinGrams * 4.0;

    // Fat: 25% of total calories (Standard healthy AMDR range 20-35%)
    $fatCals = $cals * 0.25;
    $fatGrams = round($fatCals / 9.0);

    // Carbs: Remaining caloric balance
    $remainingCals = max(0, $cals - ($proteinCals + ($fatGrams * 9.0)));
    $carbGrams = round($remainingCals / 4.0);

    // Precise calorie sum
    $totalCalcCals = ($proteinGrams * 4) + ($fatGrams * 9) + ($carbGrams * 4);

    return [
        'status' => 'calculated',
        'calorie_target' => $cals,
        'protein' => [
            'grams' => $proteinGrams,
            'calories' => $proteinGrams * 4,
            'pct' => round((($proteinGrams * 4) / $cals) * 100),
            'g_per_kg' => $proteinGramsPerKg
        ],
        'fat' => [
            'grams' => $fatGrams,
            'calories' => $fatGrams * 9,
            'pct' => round((($fatGrams * 9) / $cals) * 100)
        ],
        'carbs' => [
            'grams' => $carbGrams,
            'calories' => $carbGrams * 4,
            'pct' => round((($carbGrams * 4) / $cals) * 100)
        ],
        'total_macro_calories' => $totalCalcCals
    ];
}

function fm_calculate_hydration_target($weight_kg, $activity_level = 'moderately_active') {
    $w = floatval($weight_kg);
    if ($w <= 0) {
        return null;
    }

    // Baseline: 35ml per kg body weight
    $baseLiters = ($w * 35.0) / 1000.0;

    // Activity supplement
    $activityAddons = [
        'sedentary' => 0.0,
        'lightly_active' => 0.3,
        'moderately_active' => 0.5,
        'very_active' => 0.7,
        'extra_active' => 0.9
    ];

    $addon = $activityAddons[strtolower(trim((string)$activity_level))] ?? 0.5;
    $totalLiters = round($baseLiters + $addon, 1);

    return [
        'liters' => $totalLiters,
        'glasses' => round(($totalLiters * 1000) / 250), // 250ml glass
        'guidance' => 'General estimate for resting + training hydration. Increase fluid intake in hot climates or during high-intensity sessions.'
    ];
}

// ==============================================================================
// 8. PROPORTIONS: WAIST-TO-HEIGHT & WAIST-TO-HIP
// ==============================================================================

function fm_calculate_waist_to_height_ratio($waist_cm, $height_cm) {
    $w = floatval($waist_cm);
    $h = floatval($height_cm);
    if ($w <= 0 || $h <= 0) return null;

    $ratio = round($w / $h, 2);
    $isHealthy = ($ratio < 0.50);

    return [
        'ratio' => $ratio,
        'status' => $isHealthy ? 'Healthy (< 0.50)' : 'Elevated Risk (≥ 0.50)',
        'badge_class' => $isHealthy ? 'ios-badge-success' : 'ios-badge-warning',
        'message' => 'The Waist-to-Height ratio suggests keeping your waist circumference to less than half your height.'
    ];
}

function fm_calculate_waist_to_hip_ratio($waist_cm, $hip_cm, $gender = null) {
    $w = floatval($waist_cm);
    $hip = floatval($hip_cm);
    if ($w <= 0 || $hip <= 0) return null;

    $ratio = round($w / $hip, 2);
    $g = strtolower(trim((string)$gender));
    $threshold = ($g === 'female') ? 0.85 : 0.90;
    $isHealthy = ($ratio <= $threshold);

    return [
        'ratio' => $ratio,
        'threshold' => $threshold,
        'status' => $isHealthy ? 'Normal Risk' : 'Increased Risk',
        'badge_class' => $isHealthy ? 'ios-badge-success' : 'ios-badge-warning',
        'message' => 'WHO benchmark: Low cardiometabolic risk is defined as ≤ ' . $threshold . ' for ' . ($g ?: 'general adults') . '.'
    ];
}

// ==============================================================================
// 9. PROFILE COMPLETION CALCULATOR
// ==============================================================================

function fm_calculate_profile_completion($user, $measurementsCount = 0) {
    if (!$user) return 0;
    $score = 0;
    $checks = [
        'name' => (!empty($user['fname']) && !empty($user['lname'])) ? 15 : 0,
        'contact' => (!empty($user['mobile']) && !empty($user['email'])) ? 15 : 0,
        'location' => (!empty($user['city']) || !empty($user['address'])) ? 10 : 0,
        'dob_gender' => (!empty($user['dob']) && !empty($user['gender']) && $user['gender'] !== 'prefer_not_to_say') ? 20 : 0,
        'goal_activity' => (!empty($user['fitness_goal']) && !empty($user['activity_level'])) ? 20 : 0,
        'measurements' => ($measurementsCount > 0) ? 20 : 0
    ];

    foreach ($checks as $v) {
        $score += $v;
    }
    return min(100, $score);
}
