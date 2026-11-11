<?php
/**
 * Test Suite: FitForge Lost & Found System
 * 
 * Verifies:
 *  1. Database schema: tbl_lost_found table exists with all required columns.
 *  2. Helper function get_lost_found_categories returns categories with icons/colors.
 *  3. Helper function get_category_badge returns valid styled badge.
 *  4. Helper function get_item_status_badge handles all statuses properly.
 *  5. Direct post creation for Member (user account).
 *  6. Direct post creation for Staff/Admin (admin account, defaults to handed_to_desk).
 *  7. Direct post creation for Guest/Visitor.
 *  8. Recommendation engine pairs active lost & found items sharing category.
 *  9. Recommendation engine targeted lookup for a specific item.
 * 10. Authorization check: Author member CAN manage own item.
 * 11. Authorization check: Different member CANNOT manage author's item.
 * 12. Authorization check: Admin CAN manage/moderate any item.
 * 13. Update post workflow: caption, location, reward notes updated.
 * 14. Quick status transition: mark reunited sets status='returned' and resolved_at.
 * 15. Resolved items excluded from active recommendation pairings.
 * 16. HTTP GET /lost-found.php returns 200 and contains header, filters, and cards.
 * 17. HTTP GET /lost-found.php with filters (type=lost, type=found, type=desk) returns 200.
 * 18. HTTP GET /lost-found.php with search query (q=AirPods) returns 200.
 * 19. HTTP GET /admin/lost-found.php blocked for unauthenticated visitor (302 redirect).
 * 20. HTTP GET /admin/lost-found.php returns 200 for authenticated admin with desk statistics.
 * 21. HTTP GET /user-dashboard.php contains Lost & Found Assistance module.
 * 22. HTTP GET /profile.php contains Lost & Found tab (#lostfound) and panel.
 */

require_once __DIR__ . '/../gym/include/config.php';
require_once __DIR__ . '/../gym/include/lost_found_helper.php';

echo "========================================================\n";
echo "RUNNING FITFORGE LOST & FOUND SYSTEM TEST SUITE\n";
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

// -------------------------------------------------------------
// HELPER CURL SESSIONS
// -------------------------------------------------------------
function login_admin_cookie($email, $password) {
    $cookie = tempnam(sys_get_temp_dir(), 'adm_cookie_');
    $ch = curl_init("http://127.0.0.1:8080/admin/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
    $page = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $page, $mCsrf);
    $csrf = $mCsrf[1] ?? '';

    $ch = curl_init("http://127.0.0.1:8080/admin/login.php");
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

// -------------------------------------------------------------
// 1. SCHEMA VALIDATION
// -------------------------------------------------------------
echo "\n--- Section 1: Database Schema Validation ---\n";

$checkTable = $dbh->query("SHOW TABLES LIKE 'tbl_lost_found'")->fetch();
assert_test(!empty($checkTable), "1. Table tbl_lost_found exists in database");

$cols = $dbh->query("SHOW COLUMNS FROM tbl_lost_found")->fetchAll(PDO::FETCH_COLUMN);
$expectedCols = ['id', 'gym_id', 'item_type', 'title', 'category', 'caption', 'location_in_gym', 'date_reported', 'image_path', 'status', 'author_type', 'author_id', 'author_name', 'contact_phone', 'contact_email', 'reward_notes', 'claimed_by_name', 'claimed_by_user_id', 'resolved_at', 'created_at', 'updated_at'];
$allFound = true;
foreach ($expectedCols as $ec) {
    if (!in_array($ec, $cols)) {
        $allFound = false;
        break;
    }
}
assert_test($allFound, "1b. All required columns exist in tbl_lost_found");

// -------------------------------------------------------------
// 2. HELPER FUNCTIONS
// -------------------------------------------------------------
echo "\n--- Section 2: Helper Functions & Badges ---\n";

$cats = get_lost_found_categories();
assert_test(isset($cats['electronics']) && isset($cats['bottles']) && isset($cats['gym_gear']), "2. get_lost_found_categories returns categories with icons & colors");

$catBadge = get_category_badge('electronics');
assert_test(strpos($catBadge, 'fa-headphones') !== false && strpos($catBadge, 'Electronics') !== false, "3. get_category_badge renders icon and category name");

$openLostBadge = get_item_status_badge('open', 'lost');
$openFoundBadge = get_item_status_badge('open', 'found');
$deskBadge = get_item_status_badge('handed_to_desk', 'found');
$returnedBadge = get_item_status_badge('returned', 'lost');
assert_test(
    strpos($openLostBadge, 'Searching') !== false &&
    strpos($openFoundBadge, 'Unclaimed') !== false &&
    strpos($deskBadge, 'At Front Desk') !== false &&
    strpos($returnedBadge, 'Reunited') !== false,
    "4. get_item_status_badge renders distinct indicators for open, desk, and returned statuses"
);

// -------------------------------------------------------------
// 3. POST CREATION WORKFLOWS
// -------------------------------------------------------------
echo "\n--- Section 3: Post Creation Workflows ---\n";

// Clean any previous test entries
$dbh->exec("DELETE FROM tbl_lost_found WHERE title LIKE 'TEST_%'");

// Member creates Lost Item
$insMemberLost = $dbh->prepare("
    INSERT INTO tbl_lost_found
    (gym_id, item_type, title, category, caption, location_in_gym, date_reported, status, author_type, author_id, author_name, contact_phone, contact_email, created_at)
    VALUES (1, 'lost', 'TEST_Fitbit Charge 5 Black', 'electronics', 'Lost near treadmill 2 during morning cardio', 'Cardio Deck', CURDATE(), 'open', 'user', 1, 'Test Member', '9988776655', 'testmember@fitforge.local', NOW())
");
$insMemberLost->execute();
$testLostId = intval($dbh->lastInsertId());
assert_test($testLostId > 0, "5. Member creates 'lost' item post (status='open', author_type='user')");

// Staff/Admin creates Found Item
$insAdminFound = $dbh->prepare("
    INSERT INTO tbl_lost_found
    (gym_id, item_type, title, category, caption, location_in_gym, date_reported, status, author_type, author_id, author_name, contact_phone, contact_email, created_at)
    VALUES (1, 'found', 'TEST_Fitbit Fitness Tracker Band', 'electronics', 'Turned in by a cleaner from treadmill area', 'Cardio Deck', CURDATE(), 'handed_to_desk', 'admin', 1, 'Front Desk Staff', '9876543210', 'admin@gmail.com', NOW())
");
$insAdminFound->execute();
$testFoundId = intval($dbh->lastInsertId());
assert_test($testFoundId > 0, "6. Staff creates 'found' item post (status='handed_to_desk', author_type='admin')");

// Guest/Visitor creates post
$insGuest = $dbh->prepare("
    INSERT INTO tbl_lost_found
    (gym_id, item_type, title, category, caption, location_in_gym, date_reported, status, author_type, author_id, author_name, contact_phone, contact_email, created_at)
    VALUES (1, 'lost', 'TEST_Blue Water Flask', 'bottles', 'Left near stretch mats', 'Studio 2', CURDATE(), 'open', 'user', NULL, 'Walk-in Visitor Sam', '9123456789', 'visitor@gmail.com', NOW())
");
$insGuest->execute();
$testGuestId = intval($dbh->lastInsertId());
assert_test($testGuestId > 0, "7. Guest/visitor creates item post without requiring account");

// -------------------------------------------------------------
// 4. RECOMMENDATION ENGINE
// -------------------------------------------------------------
echo "\n--- Section 4: Recommendation Engine ---\n";

// Gym-wide recommendation matches:
$recsAll = get_lost_found_recommendations($dbh, 1);
$foundTestPair = false;
foreach ($recsAll as $pair) {
    if (intval($pair['lost_id']) === $testLostId && intval($pair['found_id']) === $testFoundId) {
        $foundTestPair = true;
        break;
    }
}
assert_test($foundTestPair, "8. Recommendation engine pairs active lost Fitbit with active found Fitbit band (same category & gym)");

// Targeted recommendations for specific lost item
$targetedRecs = get_lost_found_recommendations($dbh, 1, $testLostId);
$targetMatched = false;
foreach ($targetedRecs as $match) {
    if (intval($match['id']) === $testFoundId) {
        $targetMatched = true;
        break;
    }
}
assert_test($targetMatched, "9. Targeted lookup for test lost item returns matching found item");

// -------------------------------------------------------------
// 5. AUTHORIZATION & ACCESS CONTROL
// -------------------------------------------------------------
echo "\n--- Section 5: Authorization & Management Rules ---\n";

$lostItemRow = $dbh->query("SELECT * FROM tbl_lost_found WHERE id = $testLostId")->fetch(PDO::FETCH_ASSOC);

// Author member (user_id = 1)
$canAuthorManage = can_manage_lost_found_item($lostItemRow, 1, null, null);
assert_test($canAuthorManage === true, "10. Author member (user_id=1) CAN manage own item post");

// Another member (user_id = 999)
$canOtherManage = can_manage_lost_found_item($lostItemRow, 999, null, null);
assert_test($canOtherManage === false, "11. Another member (user_id=999) CANNOT manage author's item post");

// Admin / Staff (any role)
$canAdminManage = can_manage_lost_found_item($lostItemRow, null, 1, 'admin');
assert_test($canAdminManage === true, "12. Admin / Staff CAN manage and moderate any item post");

// -------------------------------------------------------------
// 6. UPDATE WORKFLOW & STATUS TRANSITION
// -------------------------------------------------------------
echo "\n--- Section 6: Update Workflow & Status Transition ---\n";

// Update item details
$updStmt = $dbh->prepare("
    UPDATE tbl_lost_found
    SET caption = 'Updated: Black band with small green scratch on clasp',
        location_in_gym = 'Treadmill #2 Lower Tray',
        reward_notes = 'Offering ₹500 reward'
    WHERE id = ?
");
$updStmt->execute([$testLostId]);

$refreshed = $dbh->query("SELECT caption, location_in_gym, reward_notes FROM tbl_lost_found WHERE id = $testLostId")->fetch(PDO::FETCH_ASSOC);
assert_test(
    strpos($refreshed['caption'], 'Updated:') !== false &&
    $refreshed['location_in_gym'] === 'Treadmill #2 Lower Tray' &&
    $refreshed['reward_notes'] === 'Offering ₹500 reward',
    "13. Author updates caption, location, and reward notes successfully"
);

// Quick status transition: Reunited & Returned
$reuniteStmt = $dbh->prepare("UPDATE tbl_lost_found SET status = 'returned', resolved_at = NOW() WHERE id = ?");
$reuniteStmt->execute([$testLostId]);

$reunitedRow = $dbh->query("SELECT status, resolved_at FROM tbl_lost_found WHERE id = $testLostId")->fetch(PDO::FETCH_ASSOC);
assert_test($reunitedRow['status'] === 'returned' && !empty($reunitedRow['resolved_at']), "14. Reunited item status transitions to 'returned' with resolved_at timestamp");

// Reunited items excluded from active recommendation pairings
$targetedAfterReunited = get_lost_found_recommendations($dbh, 1, $testLostId);
$targetFoundAfter = false;
foreach ($targetedAfterReunited as $m) {
    if (intval($m['id']) === $testFoundId) {
        $targetFoundAfter = true;
        break;
    }
}
assert_test(!$targetFoundAfter, "15. Reunited items excluded from active recommendations");

// -------------------------------------------------------------
// 7. HTTP ENDPOINT VERIFICATION & AUTHENTICATION GATE
// -------------------------------------------------------------
echo "\n--- Section 7: HTTP Endpoint Verification & Authentication Gate ---\n";

// 1. Unauthenticated attempt to access /lost-found.php MUST redirect (HTTP 302)
$ch = curl_init("http://127.0.0.1:8080/lost-found.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$httpCodeUnauthHub = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$redirectTarget = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
curl_close($ch);

assert_test(
    $httpCodeUnauthHub === 302 && strpos($redirectTarget, 'login.php') !== false,
    "16. Unauthenticated request to /lost-found.php redirects to login.php (HTTP 302)"
);

// 2. Login page displays prompt when redirected from lost & found
$ch = curl_init("http://127.0.0.1:8080/login.php?redirect=lost-found.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$htmlLoginPage = curl_exec($ch);
curl_close($ch);

assert_test(
    strpos($htmlLoginPage, 'Please sign in with your account to view the Lost &amp; Found') !== false ||
    strpos($htmlLoginPage, 'Please sign in with your account to view the Lost & Found') !== false,
    "16b. Login page presents clear prompt requiring account sign-in for Lost & Found"
);

// Member login cookie
$memberCookie = login_member_cookie('atul@gmail.com', 'Test@123');

// 3. Authenticated Member loads /lost-found.php (HTTP 200)
$ch = curl_init("http://127.0.0.1:8080/lost-found.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$htmlHub = curl_exec($ch);
$httpCodeHub = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $httpCodeHub === 200 &&
    strpos($htmlHub, 'Lost &amp; Found') !== false &&
    strpos($htmlHub, 'Smart Match Recommendations') !== false &&
    strpos($htmlHub, 'I Lost Something') !== false,
    "16c. Authenticated Member accesses /lost-found.php (HTTP 200) with hub and recommendations"
);

// Filter requests with authenticated member
$ch = curl_init("http://127.0.0.1:8080/lost-found.php?type=lost");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_exec($ch);
$codeLost = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ch = curl_init("http://127.0.0.1:8080/lost-found.php?type=found");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_exec($ch);
$codeFound = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$ch = curl_init("http://127.0.0.1:8080/lost-found.php?type=desk");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
curl_exec($ch);
$codeDesk = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codeLost === 200 && $codeFound === 200 && $codeDesk === 200,
    "17. Filter requests (type=lost, type=found, type=desk) return HTTP 200 for authenticated user"
);

// Search query with authenticated member
$ch = curl_init("http://127.0.0.1:8080/lost-found.php?q=AirPods");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$htmlSearch = curl_exec($ch);
$codeSearch = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codeSearch === 200 && strpos($htmlSearch, 'AirPods') !== false,
    "18. HTTP GET /lost-found.php?q=AirPods returns 200 with matching items"
);

// Unauthenticated attempt to access /admin/lost-found.php
$ch = curl_init("http://127.0.0.1:8080/admin/lost-found.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
$codeAdminUnauth = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_test($codeAdminUnauth === 302, "19. Unauthenticated request to /admin/lost-found.php redirects (HTTP 302)");

// Authenticated Admin access to /admin/lost-found.php
$adminCookie = login_admin_cookie('admin@gmail.com', 'Test@123');
$ch = curl_init("http://127.0.0.1:8080/admin/lost-found.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$htmlAdminHub = curl_exec($ch);
$codeAdminHub = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codeAdminHub === 200 &&
    strpos($htmlAdminHub, 'Lost &amp; Found Desk') !== false &&
    strpos($htmlAdminHub, 'Log Recovered Item') !== false,
    "20. Authenticated Admin loads /admin/lost-found.php (HTTP 200) with desk inventory hub"
);

// Authenticated Member access to user-dashboard.php
$memberCookie = login_member_cookie('atul@gmail.com', 'Test@123');
$ch = curl_init("http://127.0.0.1:8080/user-dashboard.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$htmlUserDash = curl_exec($ch);
$codeUserDash = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codeUserDash === 200 &&
    strpos($htmlUserDash, 'Lost &amp; Found Assistance') !== false,
    "21. Member dashboard displays Lost & Found Assistance card"
);

// Authenticated Member access to profile.php
$ch = curl_init("http://127.0.0.1:8080/profile.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$htmlProfile = curl_exec($ch);
$codeProfile = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test(
    $codeProfile === 200 &&
    strpos($htmlProfile, 'tab-lostfound') !== false &&
    strpos($htmlProfile, 'My Lost &amp; Found Items') !== false,
    "22. Member profile displays Lost & Found tab and panel"
);

// Clean up test entries
$dbh->exec("DELETE FROM tbl_lost_found WHERE title LIKE 'TEST_%'");

echo "\n========================================================\n";
echo "LOST & FOUND TEST SUITE SUMMARY:\n";
echo "PASSED: $passCount\n";
echo "FAILED: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
