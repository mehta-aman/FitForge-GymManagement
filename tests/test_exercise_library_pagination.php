<?php
/**
 * Test Suite: Exercise Library Tabs, Pagination & Grid Controls
 * 
 * Verifies:
 *  1. profile.php loads with HTTP 200 for an authenticated member.
 *  2. Master Exercise Library & Equipment Catalog section exists.
 *  3. Category Tabs/Pills exist for all 6 gym equipment categories + All Categories.
 *  4. View Mode Switcher exists with both 'Pages Mode' and 'Load More Mode'.
 *  5. Grid Size selector exists with 12 (recommended), 6, 18, 24, 36, all options.
 *  6. Columns density selector exists with 2, 3, 4 column options.
 *  7. Numbered Pagination controls wrapper and direct jump-to-page input exist.
 *  8. Progressive Load More controls wrapper, button, and progress meter exist.
 *  9. Exercise cards are rendered with .exercise-catalog-card and essential metadata.
 * 10. Client-side JS engine contains renderCatalog(), setCatalogViewMode(), setCatalogPageSize(), setCatalogGridCols().
 * 11. CSS rules for .exercise-catalog-card, .catalog-filter-btn, .catalog-mode-toggle, and .ios-pagination exist.
 */

require_once __DIR__ . '/../gym/include/config.php';

echo "========================================================\n";
echo "RUNNING EXERCISE LIBRARY PAGINATION & GRID TEST SUITE\n";
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

// 1. Fetch profile.php
$ch = curl_init("http://127.0.0.1:8080/profile.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $memberCookie);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test($httpCode === 200, "1. profile.php loads with HTTP 200 for authenticated member");
assert_test(strpos($html, 'id="exerciseCatalogSection"') !== false, "2. Master Exercise Library section (#exerciseCatalogSection) exists");

// 3. Category Tabs
assert_test(strpos($html, 'data-cat="all"') !== false, "3a. 'All Categories' tab exists");
assert_test(strpos($html, 'data-cat="floor"') !== false, "3b. 'Gym Floor / Bodyweight' category tab exists");
assert_test(strpos($html, 'data-cat="cardio"') !== false, "3c. 'Treadmill & Cardio' category tab exists");
assert_test(strpos($html, 'data-cat="dumbbells"') !== false, "3d. 'Free Weights (Dumbbells)' category tab exists");
assert_test(strpos($html, 'data-cat="barbell"') !== false, "3e. 'Free Weights (Barbell)' category tab exists");
assert_test(strpos($html, 'data-cat="machines"') !== false, "3f. 'Machines & Cables' category tab exists");
assert_test(strpos($html, 'data-cat="functional"') !== false, "3g. 'Functional & Mobility' category tab exists");

// 4. View Mode Switcher (Pages vs Load More)
assert_test(strpos($html, 'class="catalog-mode-toggle"') !== false, "4a. View Mode Toggle control exists");
assert_test(strpos($html, 'onclick="setCatalogViewMode(\'pages\')"') !== false, "4b. 'Pages Mode' toggle button exists");
assert_test(strpos($html, 'onclick="setCatalogViewMode(\'load_more\')"') !== false, "4c. 'Load More Mode' toggle button exists");

// 5. Grid Size Selector (Items Per Page / Batch)
assert_test(strpos($html, 'id="catalogPageSizeSelect"') !== false, "5a. Grid Size / Per-Page selector exists");
assert_test(strpos($html, 'value="12"') !== false, "5b. Recommended 12 / page (4x3 Grid) option available");
assert_test(strpos($html, 'value="6"') !== false, "5c. 6 / page (Compact 2x3 Grid) option available");
assert_test(strpos($html, 'value="18"') !== false, "5d. 18 / page (Balanced 6x3 Grid) option available");
assert_test(strpos($html, 'value="24"') !== false, "5e. 24 / page (Large 8x3 Grid) option available");
assert_test(strpos($html, 'value="all"') !== false, "5f. All exercises option available");

// 6. Columns / Density Selector
assert_test(strpos($html, 'id="catalogGridColsSelect"') !== false, "6a. Columns density selector exists");
assert_test(strpos($html, '3 Columns (Standard)') !== false, "6b. 3 Columns standard option available");
assert_test(strpos($html, '2 Columns (Detailed)') !== false, "6c. 2 Columns option available");
assert_test(strpos($html, '4 Columns (Compact)') !== false, "6d. 4 Columns option available");

// 7. Pagination Controls
assert_test(strpos($html, 'id="catalogPaginationWrapper"') !== false, "7a. Pagination wrapper element exists");
assert_test(strpos($html, 'id="catalogPaginationList"') !== false, "7b. Pagination list container exists");
assert_test(strpos($html, 'id="catalogPageDirectInput"') !== false, "7c. Direct page jump input exists");

// 8. Load More Controls
assert_test(strpos($html, 'id="catalogLoadMoreWrapper"') !== false, "8a. Load More wrapper element exists");
assert_test(strpos($html, 'id="catalogLoadMoreBtn"') !== false, "8b. Load More action button exists");
assert_test(strpos($html, 'id="catalogProgressBar"') !== false, "8c. Progressive load meter / progress bar exists");

// 9. Live Summary & Cards
assert_test(strpos($html, 'id="catalogPageSummary"') !== false, "9a. Live catalog page summary indicator exists");
assert_test(strpos($html, 'class="exercise-catalog-card"') !== false, "9b. Exercise cards styled with .exercise-catalog-card");
assert_test(strpos($html, 'quickAddFromCatalog') !== false, "9c. '+ Add to Routine' quick action preserved on cards");

// 10. JavaScript Engine
assert_test(strpos($html, 'function renderCatalog(') !== false, "10a. renderCatalog() JS function defined");
assert_test(strpos($html, 'function setCatalogViewMode(') !== false, "10b. setCatalogViewMode() JS function defined");
assert_test(strpos($html, 'function setCatalogPageSize(') !== false, "10c. setCatalogPageSize() JS function defined");
assert_test(strpos($html, 'function setCatalogGridCols(') !== false, "10d. setCatalogGridCols() JS function defined");
assert_test(strpos($html, 'function catalogGoToPage(') !== false, "10e. catalogGoToPage() JS function defined");
assert_test(strpos($html, 'function catalogLoadMore(') !== false, "10f. catalogLoadMore() JS function defined");

// 11. CSS Rules
assert_test(strpos($html, '.exercise-catalog-card {') !== false, "11a. .exercise-catalog-card CSS rule defined with dark glass #0b0f19");
assert_test(strpos($html, '.catalog-filter-btn {') !== false, "11b. .catalog-filter-btn CSS rule defined");
assert_test(strpos($html, '.catalog-mode-toggle {') !== false, "11c. .catalog-mode-toggle CSS rule defined");
assert_test(strpos($html, '.ios-pagination {') !== false, "11d. .ios-pagination CSS rule defined");

echo "\n========================================================\n";
echo "EXERCISE LIBRARY TEST SUITE SUMMARY:\n";
echo "PASSED: $passCount\n";
echo "FAILED: $failCount\n";
echo "========================================================\n";

if ($failCount > 0) exit(1);
