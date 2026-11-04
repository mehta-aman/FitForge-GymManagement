<?php
/**
 * FitForge / Golden Eagle Workout Preset Library & Split Generator
 * Comprehensive preset training templates and user-custom preset utilities.
 */

if (!function_exists('get_system_workout_presets')) {
    function get_system_workout_presets() {
        return [
            'ppl' => [
                'slug' => 'ppl',
                'title' => 'Push / Pull / Legs (PPL)',
                'tag' => '3-Day Hypertrophy',
                'badge_color' => '#38bdf8',
                'badge_bg' => 'rgba(56,189,248,0.15)',
                'border_color' => 'rgba(56,189,248,0.3)',
                'difficulty' => 'Intermediate',
                'days_count' => 3,
                'description' => 'Classic push-pull-legs sequence that maximizes recovery by grouping synergistic muscle groups together.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Retract scapula, control eccentric descent, explosive press.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Incline Dumbbell Bench Press (30°)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Deep stretch at bottom; squeeze clavicular pectorals at apex.'],
                    ['day' => 'Monday', 'muscle' => 'Shoulders', 'name' => 'Seated Dumbbell Shoulder Press', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Maintain braced core; press overhead without hyperextending lower back.'],
                    ['day' => 'Monday', 'muscle' => 'Triceps', 'name' => 'Cable Triceps Rope Pushdown', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Flare ropes apart at bottom for full tricep peak contraction.'],

                    ['day' => 'Wednesday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Lead with elbows downwards toward ribs; maintain upright posture.'],
                    ['day' => 'Wednesday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Neutral Grip)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Retract shoulder blades fully; 1-second pause at contraction.'],
                    ['day' => 'Wednesday', 'muscle' => 'Biceps', 'name' => 'Dumbbell Hammer Curls', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Keep palms facing inward to target brachialis and forearm thickness.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Face Pulls (Cable Rope)', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'External rotation at rear; crucial for rotator cuff and rear deltoid health.'],

                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Barbell High-Bar Back Squat', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Hit depth at or below parallel; drive out through mid-foot.'],
                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Barbell Romanian Deadlift (RDL)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Hinge deeply at hips with soft knees; feel hamstring stretch.'],
                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Leg Press (45° Incline)', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Controlled negative without rounding pelvis off pad.'],
                    ['day' => 'Friday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Leg Raises', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Strict control without momentum; curl pelvis up to ribs.'],
                ]
            ],

            'upper_lower' => [
                'slug' => 'upper_lower',
                'title' => 'Upper / Lower Power Split',
                'tag' => '4-Day Power & Strength',
                'badge_color' => '#fbbf24',
                'badge_bg' => 'rgba(251,191,36,0.15)',
                'border_color' => 'rgba(251,191,36,0.3)',
                'difficulty' => 'Intermediate',
                'days_count' => 4,
                'description' => 'Two upper-body and two lower-body workouts per week, providing 2x per week optimal muscle stimulation frequency.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 4, 'reps' => '6-8 reps', 'notes' => 'Heavy compound focus.'],
                    ['day' => 'Monday', 'muscle' => 'Back', 'name' => 'Barbell Bent-Over Row (Overhand)', 'sets' => 4, 'reps' => '6-8 reps', 'notes' => 'Torso at 45°; pull bar to upper waistline.'],
                    ['day' => 'Monday', 'muscle' => 'Shoulders', 'name' => 'Standing Barbell Overhead Press (OHP)', 'sets' => 3, 'reps' => '8-10 reps', 'notes' => 'Brace glutes and abs.'],
                    ['day' => 'Monday', 'muscle' => 'Biceps', 'name' => 'Barbell Standing Biceps Curl', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'No torso sway.'],

                    ['day' => 'Tuesday', 'muscle' => 'Legs', 'name' => 'Barbell Low-Bar Back Squat', 'sets' => 4, 'reps' => '6-8 reps', 'notes' => 'Hip hinge, sit back, knees track outward.'],
                    ['day' => 'Tuesday', 'muscle' => 'Legs', 'name' => 'Barbell Romanian Deadlift (RDL)', 'sets' => 3, 'reps' => '8-10 reps', 'notes' => 'Load posterior chain with flat back.'],
                    ['day' => 'Tuesday', 'muscle' => 'Legs', 'name' => 'Leg Extensions (Machine)', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Peak quad squeeze for 1 second.'],
                    ['day' => 'Tuesday', 'muscle' => 'Legs', 'name' => 'Standing Machine Calf Raise', 'sets' => 4, 'reps' => '15 reps', 'notes' => 'Full dorsiflexion stretch and plantarflexion peak.'],

                    ['day' => 'Thursday', 'muscle' => 'Chest', 'name' => 'Incline Dumbbell Bench Press (30°)', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Upper pectoral focus.'],
                    ['day' => 'Thursday', 'muscle' => 'Back', 'name' => 'Neutral-Grip Lat Pulldown', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Lat stretch at top, pull through elbows.'],
                    ['day' => 'Thursday', 'muscle' => 'Shoulders', 'name' => 'Cable Lateral Raise', 'sets' => 4, 'reps' => '12-15 reps', 'notes' => 'Constant cable tension across lateral head.'],
                    ['day' => 'Thursday', 'muscle' => 'Triceps', 'name' => 'Barbell Skull Crushers (Lying Tricep Ext)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Keep elbows tucked inward toward head.'],

                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Barbell Conventional Deadlift', 'sets' => 4, 'reps' => '5 reps', 'notes' => 'Full body power; drag bar up shins.'],
                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Bulgarian Split Squat (Dumbbells)', 'sets' => 3, 'reps' => '10 reps/side', 'notes' => 'Unilateral leg balance and glute activation.'],
                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Lying Hamstring Leg Curl', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Controlled eccentric tempo.'],
                    ['day' => 'Friday', 'muscle' => 'Abs & Core', 'name' => 'Ab Wheel Rollouts', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Full abdominal stretch without sagging lumbar.'],
                ]
            ],

            'arnold' => [
                'slug' => 'arnold',
                'title' => 'Arnold Golden Era Split',
                'tag' => '6-Day High Volume',
                'badge_color' => '#a855f7',
                'badge_bg' => 'rgba(168,85,247,0.15)',
                'border_color' => 'rgba(168,85,247,0.3)',
                'difficulty' => 'Advanced',
                'days_count' => 6,
                'description' => 'The legendary agonist-antagonist split pairing Chest & Back on Days 1/4, Shoulders & Arms on Days 2/5, and Legs on Days 3/6.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Pyramid weight on each set.'],
                    ['day' => 'Monday', 'muscle' => 'Back', 'name' => 'Barbell Bent-Over Row (Overhand)', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Antagonist superset pairing with bench press.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Incline Dumbbell Bench Press (30°)', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Deep clavicular stretch.'],
                    ['day' => 'Monday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Flare lats wide at the top.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Dumbbell Pullover (Lying on Bench)', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Expand rib cage and stretch lats.'],

                    ['day' => 'Tuesday', 'muscle' => 'Shoulders', 'name' => 'Seated Arnold Dumbbell Press', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Rotate wrists smoothly from supination to pronation.'],
                    ['day' => 'Tuesday', 'muscle' => 'Shoulders', 'name' => 'Standing Dumbbell Lateral Raise', 'sets' => 4, 'reps' => '12-15 reps', 'notes' => 'Lead with elbows.'],
                    ['day' => 'Tuesday', 'muscle' => 'Biceps', 'name' => 'Barbell Standing Biceps Curl', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Strict curl cadence.'],
                    ['day' => 'Tuesday', 'muscle' => 'Triceps', 'name' => 'Parallel Bar Triceps Dips (Bodyweight/Weighted)', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Stay upright to focus strictly on triceps.'],
                    ['day' => 'Tuesday', 'muscle' => 'Biceps', 'name' => 'Incline Dumbbell Biceps Curl (45°)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Long head biceps stretch.'],
                    ['day' => 'Tuesday', 'muscle' => 'Triceps', 'name' => 'Cable Overhead Rope Triceps Extension', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Deep tricep long-head extension.'],

                    ['day' => 'Wednesday', 'muscle' => 'Legs', 'name' => 'Barbell High-Bar Back Squat', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Full depth.'],
                    ['day' => 'Wednesday', 'muscle' => 'Legs', 'name' => 'Barbell Romanian Deadlift (RDL)', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Hamstring stretch.'],
                    ['day' => 'Wednesday', 'muscle' => 'Legs', 'name' => 'Leg Press (45° Incline)', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Quad pump.'],
                    ['day' => 'Wednesday', 'muscle' => 'Legs', 'name' => 'Standing Machine Calf Raise', 'sets' => 4, 'reps' => '15 reps', 'notes' => 'Full calf stretch.'],
                    ['day' => 'Wednesday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Leg Raises', 'sets' => 3, 'reps' => '15-20 reps', 'notes' => 'Strict core contraction.'],

                    ['day' => 'Thursday', 'muscle' => 'Chest', 'name' => 'Flat Dumbbell Bench Press', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Converge dumbbells at apex.'],
                    ['day' => 'Thursday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Neutral Grip)', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Squeeze rhomboids.'],
                    ['day' => 'Thursday', 'muscle' => 'Chest', 'name' => 'Cable High-to-Low Crossover Fly', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Lower chest focus.'],
                    ['day' => 'Thursday', 'muscle' => 'Back', 'name' => 'One-Arm Dumbbell Row', 'sets' => 3, 'reps' => '10 reps/side', 'notes' => 'Pull towards hip pocket.'],

                    ['day' => 'Friday', 'muscle' => 'Shoulders', 'name' => 'Standing Barbell Overhead Press (OHP)', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Strict military press.'],
                    ['day' => 'Friday', 'muscle' => 'Shoulders', 'name' => 'Cable Lateral Raise', 'sets' => 4, 'reps' => '12 reps', 'notes' => 'Constant lateral delt tension.'],
                    ['day' => 'Friday', 'muscle' => 'Biceps', 'name' => 'Dumbbell Hammer Curls', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Brachialis thickness.'],
                    ['day' => 'Friday', 'muscle' => 'Triceps', 'name' => 'Cable Triceps Straight-Bar Pushdown', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Lockout triceps.'],

                    ['day' => 'Saturday', 'muscle' => 'Legs', 'name' => 'Hack Squat (Machine)', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Deep knee flexion.'],
                    ['day' => 'Saturday', 'muscle' => 'Legs', 'name' => 'Lying Hamstring Leg Curl', 'sets' => 4, 'reps' => '12 reps', 'notes' => 'Hamstring isolation.'],
                    ['day' => 'Saturday', 'muscle' => 'Legs', 'name' => 'Walking Dumbbell Lunges', 'sets' => 3, 'reps' => '12 steps/leg', 'notes' => 'Glute burn.'],
                    ['day' => 'Saturday', 'muscle' => 'Abs & Core', 'name' => 'Cable Woodchoppers (High-to-Low)', 'sets' => 3, 'reps' => '15 reps/side', 'notes' => 'Rotational core power.'],
                ]
            ],

            'bro_split' => [
                'slug' => 'bro_split',
                'title' => 'Classic Bodybuilding Bro Split',
                'tag' => '5-Day Muscle Isolation',
                'badge_color' => '#f43f5e',
                'badge_bg' => 'rgba(244,63,94,0.15)',
                'border_color' => 'rgba(244,63,94,0.3)',
                'difficulty' => 'Intermediate',
                'days_count' => 5,
                'description' => 'Dedicated single-muscle group isolation days (Chest, Back, Shoulders, Legs, Arms). Proven for bodybuilding hypertrophy and pump.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Pyramid to top heavy working set.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Incline Dumbbell Bench Press (30°)', 'sets' => 4, 'reps' => '10-12 reps', 'notes' => 'Clavicular pectorals.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Chest Press Machine (Seated)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Controlled burnout.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Cable Mid-Chest Standing Fly', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Peak squeeze for 2 seconds.'],

                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Barbell Conventional Deadlift', 'sets' => 4, 'reps' => '6 reps', 'notes' => 'Back density and overall foundation.'],
                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Barbell Bent-Over Row (Overhand)', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Thick upper back.'],
                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'V-taper width.'],
                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Close V-Bar Grip)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Lower lat stretch and squeeze.'],

                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Seated Dumbbell Shoulder Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Front delt mass.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Standing Dumbbell Lateral Raise', 'sets' => 4, 'reps' => '12-15 reps', 'notes' => 'Medial delt capped look.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Reverse Pec Deck / Rear Delt Fly', 'sets' => 4, 'reps' => '15 reps', 'notes' => 'Posterior delt isolation.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Barbell Shrugs (Standing)', 'sets' => 4, 'reps' => '12-15 reps', 'notes' => 'Trapezius development.'],

                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Barbell High-Bar Back Squat', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Deep squatting.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Leg Press (45° Incline)', 'sets' => 4, 'reps' => '12-15 reps', 'notes' => 'Quad volume.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Lying Hamstring Leg Curl', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Hamstring hypertrophy.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Leg Extensions (Machine)', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Quad teardrop definition.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Standing Machine Calf Raise', 'sets' => 4, 'reps' => '15-20 reps', 'notes' => 'Full burn on calves.'],

                    ['day' => 'Friday', 'muscle' => 'Biceps', 'name' => 'Barbell Standing Biceps Curl', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Bicep mass.'],
                    ['day' => 'Friday', 'muscle' => 'Triceps', 'name' => 'Barbell Close-Grip Bench Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Tricep horsepower.'],
                    ['day' => 'Friday', 'muscle' => 'Biceps', 'name' => 'Incline Dumbbell Biceps Curl (45°)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Long head peak.'],
                    ['day' => 'Friday', 'muscle' => 'Triceps', 'name' => 'Cable Triceps Rope Pushdown', 'sets' => 3, 'reps' => '12-15 reps', 'notes' => 'Lateral tricep horseshoe.'],
                    ['day' => 'Friday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Leg Raises', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Finish with core.'],
                ]
            ],

            'full_body' => [
                'slug' => 'full_body',
                'title' => 'Full Body Functional Strength',
                'tag' => '3-Day Conditioning',
                'badge_color' => '#34d399',
                'badge_bg' => 'rgba(52,211,153,0.15)',
                'border_color' => 'rgba(52,211,153,0.3)',
                'difficulty' => 'Beginner to Intermediate',
                'days_count' => 3,
                'description' => 'Full-body compound movements performed every other day. Maximizes caloric expenditure, athletic coordination, and functional capability.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Legs', 'name' => 'Barbell High-Bar Back Squat', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Lower body foundation.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 4, 'reps' => '8-10 reps', 'notes' => 'Horizontal push.'],
                    ['day' => 'Monday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Neutral Grip)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Horizontal pull.'],
                    ['day' => 'Monday', 'muscle' => 'Abs & Core', 'name' => 'Plank Hold (Elbows on Gym Floor)', 'sets' => 3, 'reps' => '60 secs', 'notes' => 'Anti-extension core brace.'],

                    ['day' => 'Wednesday', 'muscle' => 'Legs', 'name' => 'Barbell Conventional Deadlift', 'sets' => 3, 'reps' => '6 reps', 'notes' => 'Posterior chain horsepower.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Standing Barbell Overhead Press (OHP)', 'sets' => 4, 'reps' => '8 reps', 'notes' => 'Vertical push.'],
                    ['day' => 'Wednesday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Vertical pull.'],
                    ['day' => 'Wednesday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Knee Tucks', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Abdominal compression.'],

                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Barbell Front Squat (Clean Grip)', 'sets' => 3, 'reps' => '8-10 reps', 'notes' => 'Quad and thoracic upright strength.'],
                    ['day' => 'Friday', 'muscle' => 'Chest', 'name' => 'Incline Dumbbell Bench Press (30°)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Incline pressing.'],
                    ['day' => 'Friday', 'muscle' => 'Legs', 'name' => 'Barbell Romanian Deadlift (RDL)', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Hamstrings and glutes.'],
                    ['day' => 'Friday', 'muscle' => 'Biceps', 'name' => 'Dumbbell Hammer Curls', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Arm finisher.'],
                ]
            ],

            'powerlifting' => [
                'slug' => 'powerlifting',
                'title' => 'Big 3 Powerlifting Peaking',
                'tag' => '4-Day Heavy Compound',
                'badge_color' => '#f59e0b',
                'badge_bg' => 'rgba(245,158,11,0.15)',
                'border_color' => 'rgba(245,158,11,0.3)',
                'difficulty' => 'Advanced',
                'days_count' => 4,
                'description' => 'Heavy compound focus built strictly around the Squat, Bench Press, and Deadlift with targeted hypertrophy accessories.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Legs', 'name' => 'Barbell Low-Bar Back Squat', 'sets' => 5, 'reps' => '5 reps', 'notes' => 'Main competition squat; 80-85% 1RM.'],
                    ['day' => 'Monday', 'muscle' => 'Legs', 'name' => 'Barbell Front Squat (Clean Grip)', 'sets' => 3, 'reps' => '6-8 reps', 'notes' => 'Quad and trunk accessory.'],
                    ['day' => 'Monday', 'muscle' => 'Legs', 'name' => 'Bulgarian Split Squat (Dumbbells)', 'sets' => 3, 'reps' => '8 reps/leg', 'notes' => 'Unilateral strength balance.'],
                    ['day' => 'Monday', 'muscle' => 'Abs & Core', 'name' => 'Ab Wheel Rollouts', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Heavy brace reinforcement.'],

                    ['day' => 'Tuesday', 'muscle' => 'Chest', 'name' => 'Barbell Flat Bench Press', 'sets' => 5, 'reps' => '5 reps', 'notes' => 'Competition pause at chest; 80-85% 1RM.'],
                    ['day' => 'Tuesday', 'muscle' => 'Triceps', 'name' => 'Barbell Close-Grip Bench Press', 'sets' => 4, 'reps' => '6-8 reps', 'notes' => 'Bench lockout power.'],
                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Barbell Bent-Over Row (Overhand)', 'sets' => 4, 'reps' => '8 reps', 'notes' => 'Upper back shelf stability.'],
                    ['day' => 'Tuesday', 'muscle' => 'Shoulders', 'name' => 'Face Pulls (Cable Rope)', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Scapular health.'],

                    ['day' => 'Thursday', 'muscle' => 'Back', 'name' => 'Barbell Conventional Deadlift', 'sets' => 5, 'reps' => '3 reps', 'notes' => 'Heavy pulls; reset each repetition.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Barbell Romanian Deadlift (RDL)', 'sets' => 3, 'reps' => '8 reps', 'notes' => 'Hamstring & glute hinge.'],
                    ['day' => 'Thursday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Lats help pull bar close to body.'],
                    ['day' => 'Thursday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Leg Raises', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Decompress spine after deadlifts.'],

                    ['day' => 'Friday', 'muscle' => 'Shoulders', 'name' => 'Standing Barbell Overhead Press (OHP)', 'sets' => 4, 'reps' => '6 reps', 'notes' => 'Overhead pressing power.'],
                    ['day' => 'Friday', 'muscle' => 'Chest', 'name' => 'Flat Dumbbell Bench Press', 'sets' => 3, 'reps' => '8-10 reps', 'notes' => 'Volume accessory.'],
                    ['day' => 'Friday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Neutral Grip)', 'sets' => 3, 'reps' => '10 reps', 'notes' => 'Mid-trap and lat thickness.'],
                    ['day' => 'Friday', 'muscle' => 'Biceps', 'name' => 'Barbell Standing Biceps Curl', 'sets' => 3, 'reps' => '10-12 reps', 'notes' => 'Bicep tendon resilience.'],
                ]
            ],

            'hiit_shred' => [
                'slug' => 'hiit_shred',
                'title' => 'HIIT & Hybrid Cardio Shred',
                'tag' => '5-Day Conditioning',
                'badge_color' => '#06b6d4',
                'badge_bg' => 'rgba(6,182,212,0.15)',
                'border_color' => 'rgba(6,182,212,0.3)',
                'difficulty' => 'Intermediate',
                'days_count' => 5,
                'description' => 'Dynamic blend of commercial treadmill intervals, rowing sprints, kettlebells, calisthenics, and functional strength for rapid fat loss.',
                'exercises' => [
                    ['day' => 'Monday', 'muscle' => 'Cardio', 'name' => 'Treadmill Incline Power Sprints (HIIT)', 'sets' => 6, 'reps' => '30s sprint / 60s rest', 'notes' => '12% incline, maximum effort sprint.'],
                    ['day' => 'Monday', 'muscle' => 'Chest', 'name' => 'Standard Bodyweight Push-ups', 'sets' => 4, 'reps' => '15-20 reps', 'notes' => 'Full chest to floor tempo.'],
                    ['day' => 'Monday', 'muscle' => 'Functional & Mobility', 'name' => 'Kettlebell Russian Swings', 'sets' => 4, 'reps' => '20 reps', 'notes' => 'Explosive hip hinge; keep core tight.'],
                    ['day' => 'Monday', 'muscle' => 'Abs & Core', 'name' => 'Mountain Climbers (Gym Floor)', 'sets' => 3, 'reps' => '45 secs', 'notes' => 'High knee cadence.'],

                    ['day' => 'Tuesday', 'muscle' => 'Cardio', 'name' => 'Concept2 Indoor Rower Intervals (500m Splits)', 'sets' => 4, 'reps' => '500m sprint', 'notes' => 'Sub 1:50 pace; powerful leg drive.'],
                    ['day' => 'Tuesday', 'muscle' => 'Back', 'name' => 'Wide-Grip Lat Pulldown', 'sets' => 4, 'reps' => '12 reps', 'notes' => 'Continuous tempo.'],
                    ['day' => 'Tuesday', 'muscle' => 'Legs', 'name' => 'Goblet Squat (Dumbbell/Kettlebell)', 'sets' => 4, 'reps' => '15 reps', 'notes' => 'Upright posture; deep squat.'],
                    ['day' => 'Tuesday', 'muscle' => 'Abs & Core', 'name' => 'Plank Hold (Elbows on Gym Floor)', 'sets' => 3, 'reps' => '60 secs', 'notes' => 'Solid core brace.'],

                    ['day' => 'Wednesday', 'muscle' => 'Cardio', 'name' => 'Assault AirBike High-Intensity Sprints', 'sets' => 5, 'reps' => '20s max / 40s rest', 'notes' => 'All-out anaerobic capacity.'],
                    ['day' => 'Wednesday', 'muscle' => 'Shoulders', 'name' => 'Dumbbell Push Press', 'sets' => 4, 'reps' => '10 reps', 'notes' => 'Use slight leg dip to propel dumbbells.'],
                    ['day' => 'Wednesday', 'muscle' => 'Functional & Mobility', 'name' => 'Kettlebell Clean and Press', 'sets' => 3, 'reps' => '8 reps/arm', 'notes' => 'Clean to rack and press overhead.'],
                    ['day' => 'Wednesday', 'muscle' => 'Abs & Core', 'name' => 'Hanging Knee Tucks', 'sets' => 3, 'reps' => '15 reps', 'notes' => 'Smooth spinal flexion.'],

                    ['day' => 'Thursday', 'muscle' => 'Cardio', 'name' => 'StairMaster StepMill Fat-Burn Intervals', 'sets' => 1, 'reps' => '20 mins', 'notes' => 'Level 8-10 continuous stepping.'],
                    ['day' => 'Thursday', 'muscle' => 'Legs', 'name' => 'Walking Dumbbell Lunges', 'sets' => 3, 'reps' => '15 steps/leg', 'notes' => 'Glute and quad endurance.'],
                    ['day' => 'Thursday', 'muscle' => 'Functional & Mobility', 'name' => 'Turkish Get-Up (Kettlebell)', 'sets' => 3, 'reps' => '4 reps/side', 'notes' => 'Total body motor control.'],
                    ['day' => 'Thursday', 'muscle' => 'Abs & Core', 'name' => 'Cable Woodchoppers (High-to-Low)', 'sets' => 3, 'reps' => '15 reps/side', 'notes' => 'Oblique burn.'],

                    ['day' => 'Friday', 'muscle' => 'Cardio', 'name' => 'Treadmill Zone 2 Steady-State Incline Walk', 'sets' => 1, 'reps' => '30 mins', 'notes' => '15% incline, 4.5 km/h; aerobic base.'],
                    ['day' => 'Friday', 'muscle' => 'Chest', 'name' => 'Parallel Bar Triceps Dips (Bodyweight/Weighted)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Controlled descent.'],
                    ['day' => 'Friday', 'muscle' => 'Back', 'name' => 'Seated Cable Row (Neutral Grip)', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Squeeze lats.'],
                    ['day' => 'Friday', 'muscle' => 'Abs & Core', 'name' => 'Ab Wheel Rollouts', 'sets' => 3, 'reps' => '12 reps', 'notes' => 'Finish core.'],
                ]
            ],
        ];
    }
}

if (!function_exists('get_user_workout_presets')) {
    function get_user_workout_presets($userId, $dbh) {
        $userId = intval($userId);
        if ($userId <= 0) return [];
        try {
            $stmt = $dbh->prepare("SELECT * FROM tbl_workout_presets WHERE user_id = :uid ORDER BY updated_at DESC");
            $stmt->execute([':uid' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['exercises'] = json_decode($r['exercises_json'] ?? '[]', true) ?: [];
            }
            return $rows;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('find_preset_by_identifier')) {
    function find_preset_by_identifier($identifier, $userId, $dbh) {
        // First check system presets by slug
        $system = get_system_workout_presets();
        if (isset($system[$identifier])) {
            return $system[$identifier];
        }

        // Second check custom preset in database by numeric ID or slug
        $userId = intval($userId);
        try {
            if (is_numeric($identifier)) {
                $stmt = $dbh->prepare("SELECT * FROM tbl_workout_presets WHERE id = :id AND (user_id = :uid OR is_system = 1)");
                $stmt->execute([':id' => intval($identifier), ':uid' => $userId]);
            } else {
                $stmt = $dbh->prepare("SELECT * FROM tbl_workout_presets WHERE slug = :slug AND (user_id = :uid OR is_system = 1)");
                $stmt->execute([':slug' => $identifier, ':uid' => $userId]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['exercises'] = json_decode($row['exercises_json'] ?? '[]', true) ?: [];
                return $row;
            }
        } catch (Exception $e) {
            // fallback
        }

        return null;
    }
}
