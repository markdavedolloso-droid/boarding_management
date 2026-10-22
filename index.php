<?php
require_once __DIR__ . '/backend/auth_init.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (empty($_SESSION['staywise_user'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="StayWise makes boarding house management easier with organized tenant records, rooms, rental contracts, and payments.">
    <title>StayWise · Boarding house management</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-button-hover">
    <link rel="stylesheet" href="assets/css/landing.css?v=20261022-seamless-feature-loop">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body class="landing-page">
    <header class="public-header">
        <a class="public-brand" href="#home" aria-label="StayWise home">
            <span class="public-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <path d="M4 14.5 16 5l12 9.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M7.5 13v14h17V13M12 27v-8h8v8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M12 13h.01M20 13h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </span>
            <span>StayWise</span>
        </a>
        <button class="public-menu-toggle" type="button" aria-controls="public-navigation" aria-expanded="false" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>
        <nav class="public-nav" id="public-navigation" aria-label="Main navigation">
            <button class="theme-toggle public-theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode">
                <iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon>
            </button>
            <a href="#home" aria-current="page">Home</a>
            <a href="#about">About</a>
            <a href="#team">Team</a>
            <a href="#contact">Contact</a>
            <a class="public-login" href="login.php">Log in</a>
            <a class="public-signup" href="signup.php">Create account</a>
        </nav>
    </header>

    <main>
        <section class="landing-hero" id="home">
            <div class="landing-hero-copy">
                <p class="landing-kicker">Boarding house management, made simple</p>
                <h1>Manage smarter.<br><span>Make room for better.</span></h1>
                <p>StayWise brings your rooms, tenants, rental agreements, and payments together in one clear workspace—so you can spend less time on paperwork and more time caring for your property.</p>
                <div class="landing-actions">
                    <a href="signup.php">Get started for free <span aria-hidden="true">→</span></a>
                    <a class="secondary" href="#features">Explore features</a>
                </div>
            </div>
            <div class="landing-visual" aria-label="Illustration of a well-managed boarding house" role="img">
                <span class="landing-orbit landing-orbit-outer" aria-hidden="true"></span>
                <span class="landing-orbit landing-orbit-outer-wide" aria-hidden="true"></span>
                <span class="landing-orbit landing-orbit-outer-oval" aria-hidden="true"></span>
                <span class="landing-orbit" aria-hidden="true"></span>
                <span class="landing-orbit landing-orbit-inner-oval" aria-hidden="true"></span>
                <span class="landing-orbit landing-orbit-inner" aria-hidden="true"></span>
                <span class="landing-orbit landing-orbit-small" aria-hidden="true"></span>
                <span class="landing-float landing-float-one">Rooms &amp; tenants, together</span>
                <span class="landing-float landing-float-two">Your property at a glance</span>
                <svg class="landing-building" viewBox="0 0 520 390" fill="none" aria-hidden="true">
                    <ellipse class="building-shadow" cx="264" cy="351" rx="174" ry="19" fill="#063b2b" fill-opacity=".75"/>
                    <path class="building-roof" d="M111 151 259 48l151 103" stroke="#19bd83" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path class="building-body" d="M137 143v183h243V143" fill="#082c21" stroke="#16875f" stroke-width="2.5"/>
                    <path class="building-windows" d="M165 170h71v66h-71zM281 170h71v66h-71z" fill="#0d5640" stroke="#27c994" stroke-width="2"/>
                    <path class="building-window-lines" d="M174 181h52M174 191h52M290 181h52M290 191h52" stroke="#80e6bf" stroke-opacity=".5" stroke-width="3" stroke-linecap="round"/>
                    <path class="building-door" d="M224 326v-70a36 36 0 0 1 72 0v70" fill="#0b7957" stroke="#27c994" stroke-width="2.5"/>
                    <path class="building-door-handle" d="M244 288h32" stroke="#c6f8df" stroke-width="3" stroke-linecap="round"/>
                    <path class="building-side-windows" d="M179 276h30v50h-30zM320 276h30v50h-30z" fill="#0b4032" stroke="#1db77f" stroke-width="2"/>
                    <path class="building-facade-line" d="M162 143h205" stroke="#31d29a" stroke-opacity=".3" stroke-width="2"/>
                    <path class="building-ground-line" d="M99 327h286" stroke="#2bbd8a" stroke-opacity=".55" stroke-width="3" stroke-linecap="round"/>
                    <circle class="building-badge" cx="403" cy="96" r="28" fill="#09241d" stroke="#1aa979" stroke-opacity=".7"/>
                    <path class="building-badge-plus" d="M396 96h14M403 89v14" stroke="#45dfae" stroke-width="2" stroke-linecap="round"/>
                    <circle class="building-badge" cx="105" cy="207" r="20" fill="#09241d" stroke="#1aa979" stroke-opacity=".65"/>
                    <path class="building-badge-plus" d="M98 207h14M105 200v14" stroke="#45dfae" stroke-width="2" stroke-linecap="round"/>
                    <circle class="building-sparkle" cx="372" cy="263" r="3" fill="#44dfa9"/>
                    <circle class="building-sparkle" cx="389" cy="263" r="3" fill="#44dfa9" fill-opacity=".55"/>
                    <circle class="building-sparkle" cx="406" cy="263" r="3" fill="#44dfa9" fill-opacity=".3"/>
                </svg>
            </div>
        </section>

        <div class="landing-ribbon" aria-label="StayWise features">
            <div class="landing-ribbon-inner">
                <div class="landing-ribbon-group">
                    <span>Tenant records</span>
                    <span>Room management</span>
                    <span>Rental contracts</span>
                    <span>Payment tracking</span>
                    <span>Useful reports</span>
                    <span>Tenant updates</span>
                </div>
                <div class="landing-ribbon-group" aria-hidden="true">
                    <span>Tenant records</span>
                    <span>Room management</span>
                    <span>Rental contracts</span>
                    <span>Payment tracking</span>
                    <span>Useful reports</span>
                    <span>Tenant updates</span>
                </div>
            </div>
        </div>

        <section class="landing-section" id="about">
            <div class="landing-about">
                <div class="landing-about-art" aria-hidden="true">
                    <svg viewBox="0 0 360 270" fill="none">
                        <rect x="28" y="28" width="304" height="212" rx="19" fill="#071b15" stroke="#1d7452" stroke-width="2"/>
                        <path d="M28 75h304" stroke="#1d7452" stroke-width="2"/>
                        <circle cx="51" cy="52" r="5" fill="#33d69e"/>
                        <circle cx="69" cy="52" r="5" fill="#208b67"/>
                        <circle cx="87" cy="52" r="5" fill="#165b45"/>
                        <rect x="52" y="98" width="112" height="112" rx="12" fill="#0b3023" stroke="#175d43"/>
                        <path d="M72 130h72M72 151h53M72 172h64" stroke="#4bd8a7" stroke-opacity=".7" stroke-width="7" stroke-linecap="round"/>
                        <rect x="185" y="100" width="121" height="45" rx="10" fill="#0a3426" stroke="#1c8058"/>
                        <circle cx="207" cy="122" r="7" fill="#40d99e"/>
                        <path d="M224 118h63M224 127h45" stroke="#9ae8c7" stroke-opacity=".65" stroke-width="4" stroke-linecap="round"/>
                        <rect x="185" y="158" width="121" height="51" rx="10" fill="#092b22" stroke="#175d43"/>
                        <path d="M199 193v-14M213 193v-24M227 193v-18M241 193v-31M255 193v-20M269 193v-27M283 193v-14" stroke="#37ce96" stroke-width="7" stroke-linecap="round"/>
                        <path d="M51 222h255" stroke="#164632" stroke-width="2"/>
                    </svg>
                </div>
                <div class="landing-about-copy">
                    <p class="landing-kicker">The StayWise difference</p>
                    <h2>Everything in its place. Everyone on the same page.</h2>
                    <p>Stop juggling paper files and scattered spreadsheets. StayWise gives boarding house owners one dependable place to manage day-to-day records and keep tenants informed.</p>
                    <ul class="landing-check-list">
                        <li>Know your rooms, occupancy, and tenant details</li>
                        <li>Keep contracts, rent, and utility records together</li>
                        <li>Share important updates through the tenant portal</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="landing-section" id="features">
            <div class="landing-section-heading">
                <p class="landing-kicker">A simpler way to stay organized</p>
                <h2>Tools for the work you do every day.</h2>
                <p>Manage the essentials from one easy-to-use dashboard, whether you’re at your desk or on your phone.</p>
            </div>
            <div class="landing-feature-grid">
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true">⌂</span>
                    <h3>Rooms &amp; tenants</h3>
                    <p>Keep room availability, tenant details, and occupancy information up to date.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true">▤</span>
                    <h3>Contracts &amp; payments</h3>
                    <p>Record rental agreements, track rent payments, and organize utility bills.</p>
                </article>
                <article class="landing-feature-card">
                    <span class="landing-feature-icon" aria-hidden="true">◉</span>
                    <h3>Updates &amp; reports</h3>
                    <p>Send tenant announcements and review clear summaries of your property activity.</p>
                </article>
            </div>
        </section>

        <section class="landing-section" id="team">
            <div class="landing-section-heading">
                <p class="landing-kicker">One place for both sides</p>
                <h2>Made for the people who make a house a home.</h2>
            </div>
            <div class="landing-role-grid">
                <article class="landing-role-card">
                    <h3>For boarding house owners</h3>
                    <p>Manage rooms, tenant records, rental contracts, payments, bills, and reports in one workspace.</p>
                </article>
                <article class="landing-role-card">
                    <h3>For tenants</h3>
                    <p>See your rental details, check recent payments, and stay up to date with manager announcements.</p>
                </article>
                <article class="landing-role-card">
                    <h3>For smoother communication</h3>
                    <p>Keep important property updates accessible without losing track of everyday details.</p>
                </article>
            </div>
        </section>

        <section class="landing-section" id="contact">
            <div class="landing-contact">
                <div>
                    <p class="landing-kicker">Ready when you are</p>
                    <h2>Make property management feel manageable.</h2>
                    <p>Create your StayWise account to organize your boarding house. Tenants can ask their manager to link their account.</p>
                </div>
                <div class="landing-actions">
                    <a href="signup.php">Get started <span aria-hidden="true">→</span></a>
                    <a class="secondary" href="login.php">Log in</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="public-footer">
        <div class="public-footer-main">
            <div class="public-footer-brand">
                <a class="public-brand" href="#home"><span>StayWise</span></a>
                <p>StayWise helps boarding house owners keep rooms, tenants, rental contracts, payments, and utility bills organized in one place. Tenants can stay informed through their own portal.</p>
            </div>
            <div class="public-footer-column">
                <h2>Explore</h2>
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#team">Team</a>
                <a href="#contact">Contact</a>
                <a href="login.php">Log in</a>
            </div>
            <div class="public-footer-column public-footer-platform">
                <h2>Platform</h2>
                <div>
                    <span>Tenant and room management</span>
                    <span>Rental contracts and occupancy</span>
                    <span>Rent and utility tracking</span>
                    <span>Tenant notifications and reports</span>
                </div>
            </div>
        </div>
        <div class="public-footer-bottom">
            <a class="public-brand" href="#home"><span>StayWise</span></a>
            <p>© <?= date('Y') ?> StayWise. All rights reserved.</p>
        </div>
    </footer>

    <script>
    (() => {
        const toggle = document.querySelector('.public-menu-toggle');
        const navigation = document.getElementById('public-navigation');
        if (!toggle || !navigation) {
            return;
        }

        const sectionLinks = [...navigation.querySelectorAll('a[href^="#"]')];
        const trackedSections = ['home', 'about', 'features', 'team', 'contact']
            .map((id) => document.getElementById(id))
            .filter(Boolean);

        const updateActiveLink = (activeHash) => {
            sectionLinks.forEach((link) => {
                if (link.getAttribute('href') === activeHash) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        const updateActiveSection = () => {
            const header = document.querySelector('.public-header');
            const activationPoint = window.scrollY
                + (header ? header.getBoundingClientRect().height : 0)
                + window.innerHeight * 0.3;
            let currentSection = trackedSections[0];

            trackedSections.forEach((section) => {
                const sectionTop = section.getBoundingClientRect().top + window.scrollY;
                if (sectionTop <= activationPoint) {
                    currentSection = section;
                }
            });

            const activeId = currentSection && currentSection.id === 'features'
                ? 'about'
                : (currentSection ? currentSection.id : 'home');
            updateActiveLink(`#${activeId}`);
        };

        updateActiveSection();
        window.addEventListener('scroll', updateActiveSection, { passive: true });
        window.addEventListener('resize', updateActiveSection);
        window.addEventListener('hashchange', () => window.setTimeout(updateActiveSection, 0));
        window.addEventListener('load', updateActiveSection, { once: true });
        window.setTimeout(updateActiveSection, 100);
        if ('IntersectionObserver' in window) {
            const sectionObserver = new IntersectionObserver(updateActiveSection, {
                rootMargin: '-20% 0px -65% 0px',
                threshold: 0,
            });
            trackedSections.forEach((section) => sectionObserver.observe(section));
        }

        const closeMenu = () => {
            navigation.removeAttribute('data-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open menu');
        };

        toggle.addEventListener('click', () => {
            const isOpen = navigation.hasAttribute('data-open');
            if (isOpen) {
                closeMenu();
            } else {
                navigation.setAttribute('data-open', 'true');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Close menu');
            }
        });
        navigation.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (link) {
                closeMenu();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && navigation.hasAttribute('data-open')) {
                closeMenu();
                toggle.focus();
            }
        });
    })();
    </script>
</body>
</html>
<?php
    exit;
endif;

require_once __DIR__ . '/backend/auth_init.php';
require_staywise_login();
$currentUser = $_SESSION['staywise_user'];
$ownerId = (int) $currentUser['owner_id'];
$activePage = 'overview';
$tenantFlashSuccess = (string) ($_SESSION['tenant_flash_success'] ?? '');
unset($_SESSION['tenant_flash_success']);

if ($currentUser['role'] === 'tenant') {
    $tenantId = (int) $currentUser['tenant_id'];
    $stmt = $pdo->prepare(
        "SELECT c.contract_id, c.start_date, c.end_date, r.room_number, r.floor, r.monthly_rent
         FROM rental_contracts c
         JOIN rooms r ON r.room_id = c.room_id AND r.owner_id = c.owner_id
         WHERE c.tenant_id = :tenant_id AND c.owner_id = :owner_id
           AND c.contract_status = 'active'
           AND c.start_date <= CURDATE()
           AND (c.end_date IS NULL OR c.end_date >= CURDATE())
         ORDER BY c.start_date DESC LIMIT 1"
    );
    $stmt->execute([':tenant_id' => $tenantId, ':owner_id' => $ownerId]);
    $contract = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT p.* FROM payments p
         JOIN rental_contracts c ON c.contract_id = p.contract_id AND c.owner_id = p.owner_id
         WHERE p.owner_id = :owner_id AND c.tenant_id = :tenant_id
         ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 8'
    );
    $stmt->execute([':owner_id' => $ownerId, ':tenant_id' => $tenantId]);
    $payments = $stmt->fetchAll();

    $rentBalance = 0.0;
    $utilityBalance = 0.0;
    if ($contract) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE owner_id = :owner_id AND contract_id = :contract_id
               AND billing_month = DATE_FORMAT(CURDATE(), '%Y-%m-01') AND payment_status = 'paid'"
        );
        $stmt->execute([':owner_id' => $ownerId, ':contract_id' => $contract['contract_id']]);
        $rentBalance = max(0, (float) $contract['monthly_rent'] - (float) $stmt->fetchColumn());

    }
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(b.total_amount), 0) FROM utility_bills b
         JOIN rental_contracts c ON c.contract_id = b.contract_id AND c.owner_id = b.owner_id
         WHERE b.owner_id = :owner_id AND c.tenant_id = :tenant_id AND b.bill_status = 'unpaid'"
    );
    $stmt->execute([':owner_id' => $ownerId, ':tenant_id' => $tenantId]);
    $utilityBalance = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT * FROM notifications WHERE tenant_id = :tenant_id AND owner_id = :owner_id
         ORDER BY date_sent DESC LIMIT 5'
    );
    $stmt->execute([':tenant_id' => $tenantId, ':owner_id' => $ownerId]);
    $notifications = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE owner_id = :owner_id');
    $stmt->execute([':owner_id' => $ownerId]);
    $totalTenants = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE owner_id = :owner_id AND date_registered = CURDATE()');
    $stmt->execute([':owner_id' => $ownerId]);
    $registeredToday = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM rental_contracts
         WHERE owner_id = :owner_id AND contract_status = 'active'
           AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $activeContracts = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM payments
         WHERE owner_id = :owner_id AND payment_status = \'paid\'
           AND payment_date >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')
           AND payment_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, \'%Y-%m-01\')'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $monthlyIncome = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT r.room_id, r.room_number, r.capacity,
                COUNT(c.contract_id) AS occupant_count
         FROM rooms r
         LEFT JOIN rental_contracts c
           ON c.room_id = r.room_id AND c.owner_id = r.owner_id
          AND c.contract_status = \'active\' AND c.start_date <= CURDATE()
          AND (c.end_date IS NULL OR c.end_date >= CURDATE())
         WHERE r.owner_id = :owner_id AND r.status = \'available\'
         GROUP BY r.room_id, r.room_number, r.capacity'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $roomRows = $stmt->fetchAll();
    $availableSpaces = 0;
    $totalCapacity = 0;
    $occupiedSpaces = 0;
    foreach ($roomRows as $room) {
        $totalCapacity += (int) $room['capacity'];
        $occupiedSpaces += (int) $room['occupant_count'];
        $availableSpaces += max(0, (int) $room['capacity'] - (int) $room['occupant_count']);
    }
    $occupancyRate = $totalCapacity > 0 ? (int) round($occupiedSpaces * 100 / $totalCapacity) : 0;

    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE owner_id = :owner_id ORDER BY tenant_id DESC LIMIT 5');
    $stmt->execute([':owner_id' => $ownerId]);
    $recentTenants = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT l.activity, l.created_at, u.full_name
         FROM activity_logs l JOIN app_users u ON u.user_id = l.user_id
         WHERE l.owner_id = :owner_id ORDER BY l.created_at DESC LIMIT 5'
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $recentActivity = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications
         WHERE owner_id = :owner_id AND date_sent >= DATE_SUB(NOW(), INTERVAL 3 DAY)
           AND title = 'Payment reminder'"
    );
    $stmt->execute([':owner_id' => $ownerId]);
    $recentReminderCount = (int) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StayWise · Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20261022-no-sidebar-icons">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-home-nav-theme">
    <script src="https://code.iconify.design/iconify-icon/2.3.0/iconify-icon.min.js" defer></script>
    <script src="assets/js/theme.js?v=20261022-iconify"></script>
</head>
<body>
    <?php require __DIR__ . '/Include/sidebar.php'; ?>

    <main class="home-content">
        <header class="topbar">
            <p class="topbar-label">StayWise <span aria-hidden="true">/</span> Overview</p>
            <div class="topbar-actions">
                <?php if ($currentUser['role'] === 'owner'): ?>
                    <a class="button secondary" href="view_tenants.php">⌕ Search records</a>
                <?php endif; ?>
                <span class="topbar-user"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to light mode">
                    <iconify-icon class="theme-toggle-icon" icon="lucide:sun" aria-hidden="true"></iconify-icon>
                </button>
            </div>
        </header>

        <?php if ($currentUser['role'] === 'tenant'): ?>
            <div class="page-head">
                <div>
                    <p class="eyebrow">Tenant portal</p>
                    <h1>Welcome, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p style="margin:5px 0 0">Your room, rent, and recent account updates.</p>
                </div>
            </div>
            <section class="stats" aria-label="Your rental summary">
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">⌂</span>
                    <div class="stat-label">Room</div>
                    <div class="stat-value"><?= $contract ? htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8') : 'Unassigned' ?></div>
                    <div class="stat-foot"><?= $contract ? 'Floor ' . (int) $contract['floor'] : 'Contact your manager' ?></div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">₱</span>
                    <div class="stat-label">Current balance</div>
                    <div class="stat-value">₱<?= number_format($rentBalance + $utilityBalance, 2) ?></div>
                    <div class="stat-foot">Rent plus unpaid utilities</div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">▤</span>
                    <div class="stat-label">Recent payments</div>
                    <div class="stat-value"><?= count($payments) ?></div>
                    <div class="stat-foot">Latest payment records</div>
                </div>
                <div class="stat">
                    <span class="stat-icon" aria-hidden="true">◉</span>
                    <div class="stat-label">Notifications</div>
                    <div class="stat-value"><?= count($notifications) ?></div>
                    <div class="stat-foot">Recent account updates</div>
                </div>
            </section>
            <section class="dashboard-grid">
                <div class="panel">
                    <div class="panel-head"><div><h2>Payment history</h2><p>Your latest rent payments.</p></div></div>
                    <table>
                        <thead><tr><th>Payment date</th><th>Billing month</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['payment_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($payment['billing_month'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
                                <td><?= htmlspecialchars(ucfirst($payment['payment_status']), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="4" class="empty-state">No payments recorded yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>Current rental</h2><p>Your agreement and outstanding charges.</p></div></div>
                    <?php if ($contract): ?>
                        <p><strong>Room <?= htmlspecialchars($contract['room_number'], ENT_QUOTES, 'UTF-8') ?></strong> · Floor <?= (int) $contract['floor'] ?></p>
                        <p>Rent: ₱<?= number_format((float) $contract['monthly_rent'], 2) ?> per month</p>
                        <p>Contract: <?= htmlspecialchars($contract['start_date'], ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($contract['end_date'] ?: 'open-ended', ENT_QUOTES, 'UTF-8') ?></p>
                        <p>Unpaid utilities: ₱<?= number_format($utilityBalance, 2) ?></p>
                    <?php else: ?>
                        <p>No active rental agreement is linked to your tenant account.</p>
                    <?php endif; ?>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>Notifications</h2><p>Updates from your manager.</p></div><a href="notifications.php">View all →</a></div>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="activity-item">
                            <strong><?= htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= nl2br(htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8')) ?></p>
                            <small><?= htmlspecialchars($notification['date_sent'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?><p class="empty-state">No notifications yet.</p><?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="page-head">
                <div>
                    <p class="eyebrow">Boarding house management</p>
                    <h1>Your property, clearly managed</h1>
                    <p style="margin:5px 0 0">Keep tenant information organized in one simple workspace.</p>
                </div>
                <button class="button" type="button" data-open-tenant-modal>＋ Add tenant</button>
            </div>

            <section class="stats" aria-label="Property statistics">
                <div class="stat"><span class="stat-icon" aria-hidden="true">♙</span><div class="stat-label">Total tenants</div><div class="stat-value"><?= number_format($totalTenants) ?></div><div class="stat-foot">● Registered tenant records</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">⌂</span><div class="stat-label">Vacant spaces</div><div class="stat-value"><?= number_format($availableSpaces) ?></div><div class="stat-foot"><?= number_format($occupancyRate) ?>% occupancy · updates automatically</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">₱</span><div class="stat-label">Monthly income</div><div class="stat-value">₱<?= number_format($monthlyIncome, 2) ?></div><div class="stat-foot">Paid this month</div></div>
                <div class="stat"><span class="stat-icon" aria-hidden="true">▤</span><div class="stat-label">Active contracts</div><div class="stat-value"><?= number_format($activeContracts) ?></div><div class="stat-foot"><?= number_format($registeredToday) ?> tenants added today</div></div>
            </section>

            <section class="dashboard-grid">
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Tenant management</h2><p>Keep every resident record up to date.</p></div></div>
                    <p>Register tenants, update their details, search contact information, and review existing records from one workspace.</p>
                    <div class="quick-actions">
                        <button class="button" type="button" data-open-tenant-modal>＋ Register tenant</button>
                        <a class="button secondary" href="view_tenants.php">View all tenants →</a>
                    </div>
                    <form class="inline-form" method="GET" action="view_tenants.php" role="search">
                        <input type="search" name="keyword" placeholder="Search tenant records..." aria-label="Search tenant records">
                        <button type="submit">Search</button>
                    </form>
                </div>
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Recently added tenants</h2><p>Your latest tenant records.</p></div><a href="view_tenants.php">View all →</a></div>
                    <table>
                        <thead><tr><th>Tenant</th><th>Gender</th><th>Contact</th><th>Date registered</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentTenants as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['contact_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($row['date_registered'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><a href="view_tenants.php?edit=<?= (int) $row['tenant_id'] ?>">Edit record</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentTenants): ?><tr><td colspan="5" class="empty-state">No tenant records yet. Register your first tenant to get started.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel recent-panel">
                    <div class="panel-head"><div><h2>Recent activity</h2><p>Latest changes to your records.</p></div><a href="activity_logs.php">View logs →</a></div>
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <strong><?= htmlspecialchars($activity['activity'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars($activity['full_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($activity['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentActivity): ?><p class="empty-state">No activity recorded yet.</p><?php endif; ?>
                    <?php if ($recentReminderCount > 0): ?><p class="alert"><?= number_format($recentReminderCount) ?> payment reminders sent in the last 3 days.</p><?php endif; ?>
                </div>
            </section>
            <?php if ($tenantFlashSuccess !== ''): ?><div class="alert" role="status"><?= htmlspecialchars($tenantFlashSuccess, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php $tenantModalReturn = 'index.php'; require __DIR__ . '/modals/tenant_modal.php'; ?>
        <?php endif; ?>
    </main>
</body>
</html>
