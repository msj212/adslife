<?php
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../config/database.php';

applyCORS();
Auth::requireRole('admin');

$db = Database::getInstance();

// ── Totals ───────────────────────────────────────────────────
$users   = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$vendors = (int)$db->query('SELECT COUNT(*) FROM vendors WHERE status = "approved"')->fetchColumn();
$offers  = (int)$db->query('SELECT COUNT(*) FROM offers WHERE is_active = 1')->fetchColumn();
$totalOffers = (int)$db->query('SELECT COUNT(*) FROM offers')->fetchColumn();

// ── Pending counts ───────────────────────────────────────────
$pendingVendors = (int)$db->query('SELECT COUNT(*) FROM vendors WHERE status = "pending_review"')->fetchColumn();
$openTickets    = (int)$db->query('SELECT COUNT(*) FROM support_tickets WHERE status = "open"')->fetchColumn();
$pendingBanners = (int)$db->query('SELECT COUNT(*) FROM banner_ad_requests WHERE status = "pending"')->fetchColumn();
$pendingSpotlights = (int)$db->query('SELECT COUNT(*) FROM spotlight_requests WHERE status = "pending"')->fetchColumn();
$fraudFlags     = (int)$db->query('SELECT COUNT(*) FROM fraud_flags WHERE status = "pending"')->fetchColumn();

// ── Revenue (sum of paid payments) ──────────────────────────
$rev = $db->query('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = "paid"')->fetchColumn();

// ── New users this month vs last month ───────────────────────
$usersThisMonth = (int)$db->query(
    'SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())'
)->fetchColumn();
$usersLastMonth = (int)$db->query(
    'SELECT COUNT(*) FROM users WHERE MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))'
)->fetchColumn();
$userGrowth = $usersLastMonth > 0 ? round(($usersThisMonth - $usersLastMonth) / $usersLastMonth * 100, 1) : ($usersThisMonth > 0 ? 100 : 0);

// ── Total interactions ───────────────────────────────────────
$interactions = (int)$db->query('SELECT COUNT(*) FROM user_interactions')->fetchColumn();
$interactionsToday = (int)$db->query('SELECT COUNT(*) FROM user_interactions WHERE DATE(created_at) = CURDATE()')->fetchColumn();

// ── User role breakdown ──────────────────────────────────────
$roles = $db->query('SELECT role, COUNT(*) as cnt FROM users GROUP BY role')->fetchAll();
$roleBreakdown = ['user' => 0, 'vendor' => 0, 'admin' => 0];
foreach ($roles as $r) { $roleBreakdown[$r['role']] = (int)$r['cnt']; }

// ── New users last 7 days (daily) ────────────────────────────
$dailyUsers = $db->query(
    'SELECT DATE(created_at) AS d, COUNT(*) AS cnt
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(created_at)
     ORDER BY d ASC'
)->fetchAll();

// ── Recent activity (last 5 of each type) ───────────────────
$recentUsers = $db->query(
    'SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$recentVendors = $db->query(
    'SELECT v.id, v.business_name, v.status, v.subscription_plan, v.created_at, u.email
     FROM vendors v JOIN users u ON v.user_id = u.id
     ORDER BY v.created_at DESC LIMIT 5'
)->fetchAll();

jsonSuccess([
    'totals' => [
        'users'           => $users,
        'vendors'         => $vendors,
        'offers'          => $offers,
        'total_offers'    => $totalOffers,
        'interactions'    => $interactions,
        'revenue'         => round((float)$rev, 2),
    ],
    'pending' => [
        'vendors'   => $pendingVendors,
        'tickets'   => $openTickets,
        'banners'   => $pendingBanners,
        'spotlights'=> $pendingSpotlights,
        'fraud'     => $fraudFlags,
    ],
    'users' => [
        'this_month'   => $usersThisMonth,
        'last_month'   => $usersLastMonth,
        'growth_pct'   => $userGrowth,
        'today_active' => $interactionsToday,
        'role_breakdown' => $roleBreakdown,
    ],
    'daily_signups'  => $dailyUsers,
    'recent_users'   => $recentUsers,
    'recent_vendors' => $recentVendors,
]);
