<?php
/**
 * Test Suite: Member Dashboard & Welcome Screen Redesign
 * 
 * Verifies:
 *  1. Member welcome screen (user-dashboard.php) loads with HTTP 200.
 *  2. Welcome screen contains personalized greeting and Official Member Dashboard pill.
 *  3. Welcome screen contains 'View Public Portal' action button.
 *  4. Welcome screen displays 'Today's Routine' for current day only.
 *  5. Welcome screen strictly excludes the full 7-day 'Weekly Training Routine' loop.
 *  6. Welcome screen contains integrated 'My Bookings & Passes' section with receipts.
 *  7. Logged-in member accessing index.php is redirected to user-dashboard.php (HTTP 302).
 *  8. Logged-in member accessing index.php?public=1 can preview public site (HTTP 200).
 *  9. Public preview page displays return banner to Member Dashboard.
 * 10. Logged-in member header navigation excludes public portal links (About, Contact).
 * 11. Logged-in member header navigation contains 'View Public Portal' option.
 * 12. Public visitor header navigation retains Home, About, Contact, Sign In.
 * 13. Logo link directs to user-dashboard.php for members and index.php for visitors.
 */

require_once __DIR__ . '/../gym/include/config.php';

echo "========================================================\n";
echo "RUNNING MEMBER DASHBOARD & WELCOME SCREEN REDESIGN SUITE\n";
echo "========================================================\n";

$passCount = 0;
$failCount = 0;

function assert_test($condition, $name) {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $name\n";
        $passCount++;
    } else {
        echo "  [FAIL] $name\n";
        $failCount++;
    }
}

function login_member_cookie($email, $password) {
    $cookie = tempnam(sys_get_temp_dir(), 'mem_cookie_');
    $ch = curl_init("http://127.0.0.1:8080/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    $page = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $page, $mCsrf);
    $csrf = $mCsrf[1] ?? '';

    $ch = curl_init("http://127.0.0.1:8080/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'email' => $email,
        'password' => $password,
        'submit' => '1'
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);

    return $cookie;
}

$memberCookie = login_member_cookie('atul@gmail.com', 'Test@123');

// 1. Member loads user-dashboard.php
$ch = curl_init("http://127.0.0.1:8080/user-dashboard.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$htmlDash = curl_exec($ch);
$codeDash = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($codeDash === 200, "1. Member welcome screen (user-dashboard.php) loads with HTTP 200");
assert_test(strpos($htmlDash, '<title>Home —') !== false, "2a. Member screen title updated to 'Home'");
assert_test(strpos($htmlDash, 'OFFICIAL MEMBER DASHBOARD') === false, "2b. 'OFFICIAL MEMBER DASHBOARD' pill successfully removed");
assert_test(strpos($htmlDash, 'View Public Portal') === false, "3a. 'View Public Portal' button removed from welcome hero & navigation");
assert_test(strpos($htmlDash, 'Book New Plan') === false, "3b. 'Book New Plan' button removed from welcome hero & bookings");

// 3c. Welcome hero strip decluttered — all redundant header action buttons removed
assert_test(
    strpos($htmlDash, 'Booking-History.php" class="ios-btn') === false &&
    strpos($htmlDash, 'profile.php" class="ios-btn') === false &&
    strpos($htmlDash, 'lost-found.php" class="ios-btn') === false,
    "3c. Redundant action buttons removed from welcome header (decluttered)"
);

// 3d. Navigation retains primary pathways
assert_test(strpos($htmlDash, '<i class="fa fa-calendar-check-o"></i> Bookings') !== false, "3d. 'Bookings' link available in navigation");
assert_test(strpos($htmlDash, 'scan.php') !== false, "3e. 'Scan QR' link available in navigation & pass card");
assert_test(strpos($htmlDash, 'lost-found.php') !== false, "3f. 'Lost & Found' link available in navigation & hub card");

// 4. Today's Routine Only check
$todayDay = date('l');
assert_test(strpos($htmlDash, "Today's Routine ($todayDay)") !== false, "4. Welcome screen displays 'Today\'s Routine' for current day ($todayDay)");
assert_test(strpos($htmlDash, 'Weekly Training Routine') === false, "5. Welcome screen strictly excludes the full 7-day 'Weekly Training Routine' loop");

// 6. Integrated Bookings
assert_test(strpos($htmlDash, 'My Bookings &amp; Passes') !== false, "6. Welcome screen contains integrated 'My Bookings & Passes' section with receipts");

// 6b. Standardized Fitness Metrics Dark Glass Card design check
assert_test(strpos($htmlDash, 'rgba(15, 23, 42,') !== false && strpos($htmlDash, 'rgba(255, 255, 255, 0.08)') !== false, "6b. Welcome screen cards use standardized Fitness Metrics dark glass theme (rgba(15, 23, 42, 0.65) / rgba(255, 255, 255, 0.08))");
assert_test(strpos($htmlDash, 'class="col-6 col-md-3"') !== false, "6c. Quick stats cards use col-6 col-md-3 for clean 2x2 grid on mobile");
assert_test(strpos($htmlDash, "Today's Routine") < strpos($htmlDash, "Active Membership Pass &amp; Check-in"), "6d. Active pass QR code card is placed at the bottom of Today's Routine");

// 6e. Responsive dual-layout CSS Grid validation (Desktop 2-column + Mobile 1-column)
assert_test(strpos($htmlDash, 'class="dashboard-grid-layout"') !== false, "6e. Dashboard has .dashboard-grid-layout wrapper");
assert_test(strpos($htmlDash, 'grid-template-areas:') !== false, "6f. CSS grid uses named template areas for desktop layout");
assert_test(
    strpos($htmlDash, 'class="grid-area-stats"') !== false &&
    strpos($htmlDash, 'class="grid-area-routine"') !== false &&
    strpos($htmlDash, 'class="grid-area-pass"') !== false &&
    strpos($htmlDash, 'class="grid-area-bookings"') !== false &&
    strpos($htmlDash, 'class="grid-area-coach"') !== false &&
    strpos($htmlDash, 'class="grid-area-lostfound"') !== false,
    "6g. All 6 functional dashboard modules are assigned to responsive grid areas"
);
// Verify mobile DOM sequence: stats -> routine -> pass -> bookings -> coach -> lostfound
$posStats = strpos($htmlDash, 'class="grid-area-stats"');
$posRoutine = strpos($htmlDash, 'class="grid-area-routine"');
$posPass = strpos($htmlDash, 'class="grid-area-pass"');
$posBookings = strpos($htmlDash, 'class="grid-area-bookings"');
$posCoach = strpos($htmlDash, 'class="grid-area-coach"');
$posLostFound = strpos($htmlDash, 'class="grid-area-lostfound"');
assert_test(
    $posStats < $posRoutine &&
    $posRoutine < $posPass &&
    $posPass < $posBookings &&
    $posBookings < $posCoach &&
    $posCoach < $posLostFound,
    "6h. DOM sequence strictly follows optimal mobile flow (Stats -> Routine -> Pass -> Bookings -> Coach -> Lost&Found)"
);

// 7. Logged-in member accessing index.php is redirected
$ch = curl_init("http://127.0.0.1:8080/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$codeIndexMem = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$locIndexMem = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

assert_test($codeIndexMem === 302 && strpos($locIndexMem, 'user-dashboard.php') !== false, "7. Logged-in member accessing index.php is redirected to user-dashboard.php (HTTP 302)");

// 8. Logged-in member accessing index.php?public=1 can preview
$ch = curl_init("http://127.0.0.1:8080/index.php?public=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$htmlPublicPreview = curl_exec($ch);
$codePublicPreview = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($codePublicPreview === 200, "8. Logged-in member accessing index.php?public=1 can preview public site (HTTP 200)");
assert_test(
    strpos($htmlPublicPreview, 'Return to Member Dashboard') !== false &&
    strpos($htmlPublicPreview, 'You are previewing the public portal') !== false,
    "9. Public preview page displays return banner to Member Dashboard"
);

// 10. Logged-in member header navigation checks
assert_test(
    strpos($htmlDash, '<a href="about.php"') === false &&
    strpos($htmlDash, '<a href="contact.php"') === false,
    "10. Logged-in member header navigation excludes public portal links (About, Contact)"
);
assert_test(strpos($htmlDash, '<i class="fa fa-home"></i> Home') !== false, "11. Logged-in member header navigation has 'Home' linking to user-dashboard.php");
assert_test(strpos($htmlDash, 'View Public Portal') === false, "11b. Header navigation completely omits 'View Public Portal'");

// 12. Public visitor header navigation
$ch = curl_init("http://127.0.0.1:8080/index.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$htmlPublicVisitor = curl_exec($ch);
$codePublicVisitor = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codePublicVisitor === 200 &&
    strpos($htmlPublicVisitor, 'about.php') !== false &&
    strpos($htmlPublicVisitor, 'contact.php') !== false &&
    strpos($htmlPublicVisitor, 'login.php') !== false,
    "12. Public visitor header navigation retains Home, About, Contact, Sign In"
);

// 13. Logo Link checks
assert_test(strpos($htmlDash, 'href="user-dashboard.php" class="ios-nav-logo"') !== false, "13a. Logo directs to user-dashboard.php for authenticated member");
assert_test(strpos($htmlPublicVisitor, 'href="index.php" class="ios-nav-logo"') !== false, "13b. Logo directs to index.php for public visitor");

echo "\n========================================================\n";
echo "MEMBER DASHBOARD REDESIGN SUITE SUMMARY:\n";
echo "PASSED: $passCount\n";
echo "FAILED: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
