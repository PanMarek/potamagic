<?php
require_once __DIR__ . '/config/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------
// 1. Fetch Slider Images
// ----------------------------------------------------
$slider_images = $pdo->query("SELECT * FROM slider_images ORDER BY id DESC")->fetchAll();

// ----------------------------------------------------
// 2. Fetch Dashboard Statistics
// ----------------------------------------------------
$stats = [
    'total_activations' => 0,
    'total_qsos' => 0,
    'total_parks' => 0
];

try {
    $stats['total_activations'] = $pdo->query("SELECT COUNT(*) FROM activations")->fetchColumn();
    $stats['total_qsos'] = $pdo->query("SELECT SUM(qso_count) FROM activations")->fetchColumn() ?: 0;
    $stats['total_parks'] = $pdo->query("SELECT COUNT(DISTINCT park_reference) FROM activations")->fetchColumn();
} catch (PDOException $e) {
    // Fail silently or handle
}

// ----------------------------------------------------
// 3. Process Search, Filters & Sorting
// ----------------------------------------------------
$search = trim($_GET['search'] ?? '');
$filter_band = trim($_GET['band'] ?? '');
$filter_mode = trim($_GET['mode'] ?? '');
$sort_by = trim($_GET['sort'] ?? 'date_desc');
$group_by = trim($_GET['group'] ?? 'none');

$query = "SELECT a.*, u.username 
          FROM activations a 
          JOIN users u ON a.user_id = u.id 
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (a.park_name LIKE :search OR a.park_reference LIKE :search OR a.transceiver LIKE :search OR u.username LIKE :search)";
    $params['search'] = "%{$search}%";
}

if ($filter_band !== '') {
    $query .= " AND a.bands LIKE :band";
    $params['band'] = "%{$filter_band}%";
}

if ($filter_mode !== '') {
    $query .= " AND a.modes LIKE :mode";
    $params['mode'] = "%{$filter_mode}%";
}

// Sorting
switch ($sort_by) {
    case 'date_asc':
        $query .= " ORDER BY a.activation_date ASC, a.created_at ASC";
        break;
    case 'qso_desc':
        $query .= " ORDER BY a.qso_count DESC, a.activation_date DESC";
        break;
    case 'qso_asc':
        $query .= " ORDER BY a.qso_count ASC, a.activation_date DESC";
        break;
    case 'date_desc':
    default:
        $query .= " ORDER BY a.activation_date DESC, a.created_at DESC";
        break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activations = $stmt->fetchAll();
} catch (PDOException $e) {
    $activations = [];
    $error = "Failed to load activations: " . $e->getMessage();
}

// Fetch available bands and modes for dropdowns
$available_bands = $pdo->query("SELECT name FROM bands ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
$available_modes = $pdo->query("SELECT name FROM modes ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);

// ----------------------------------------------------
// 4. Fetch Chart Data (Bands, Modes, Locations)
// ----------------------------------------------------
$band_chart_data = [];
$mode_chart_data = [];
$loc_chart_data = [];

if ($stats['total_activations'] > 0) {
    // Process bands chart data
    $band_counts = [];
    foreach ($pdo->query("SELECT bands FROM activations")->fetchAll(PDO::FETCH_COLUMN) as $b_str) {
        $parts = array_map('trim', explode(',', $b_str ?? ''));
        foreach ($parts as $p) {
            if ($p) $band_counts[$p] = ($band_counts[$p] ?? 0) + 1;
        }
    }
    arsort($band_counts);
    $band_chart_data = array_slice($band_counts, 0, 8); // Top 8 bands
    
    // Process modes chart data
    $mode_counts = [];
    foreach ($pdo->query("SELECT modes FROM activations")->fetchAll(PDO::FETCH_COLUMN) as $m_str) {
        $parts = array_map('trim', explode(',', $m_str ?? ''));
        foreach ($parts as $p) {
            if ($p) $mode_counts[$p] = ($mode_counts[$p] ?? 0) + 1;
        }
    }
    arsort($mode_counts);
    $mode_chart_data = array_slice($mode_counts, 0, 6); // Top 6 modes

    // Process locations (Entity Prefixes)
    $loc_counts = [];
    foreach ($pdo->query("SELECT park_reference FROM activations")->fetchAll(PDO::FETCH_COLUMN) as $ref) {
        $prefix = explode('-', $ref)[0];
        if ($prefix) $loc_counts[$prefix] = ($loc_counts[$prefix] ?? 0) + 1;
    }
    arsort($loc_counts);
    $loc_chart_data = array_slice($loc_counts, 0, 8); // Top 8 locations
}

// ----------------------------------------------------
// 5. Apply Grouping in PHP
// ----------------------------------------------------
$grouped_activations = [];

if ($group_by === 'none' || empty($activations)) {
    $grouped_activations['All Activations'] = $activations;
} else {
    foreach ($activations as $act) {
        $group_name = 'Other';
        
        if ($group_by === 'location') {
            // Group by country/region prefix (e.g. US, PL, VE)
            $group_name = explode('-', $act['park_reference'])[0];
        } elseif ($group_by === 'month') {
            // Group by Month (e.g. June 2026)
            $group_name = date('F Y', strtotime($act['activation_date']));
        } elseif ($group_by === 'activator') {
            // Group by activator callsign
            $group_name = strtoupper($act['username']);
        }
        
        $grouped_activations[$group_name][] = $act;
    }
    // Sort groups alphabetically or logically
    if ($group_by === 'month') {
        // Sort months chronologically desc
        uksort($grouped_activations, function($a, $b) {
            return strtotime($b) - strtotime($a);
        });
    } else {
        ksort($grouped_activations);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Hero Picture Slider -->
<section class="hero-slider-section">
    <div class="slider-container">
        <?php if (empty($slider_images)): ?>
            <!-- Default Slide 1 -->
            <div class="slide active" style="background-image: url('uploads/slider/default1.jpg');">
                <div class="slide-overlay">
                    <div class="slide-content">
                        <h1 class="slide-title">Log Portable Field Activations</h1>
                        <p class="slide-desc">Track your gear, parking access, signal coverage, and share useful localization tips with other operators.</p>
                        <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Join the Community</a>
                    </div>
                </div>
            </div>
            <!-- Default Slide 2 -->
            <div class="slide" style="background-image: url('uploads/slider/default2.jpg');">
                <div class="slide-overlay">
                    <div class="slide-content">
                        <h1 class="slide-title">POTA.app Integration</h1>
                        <p class="slide-desc">Enter any global park reference code, and watch the system lookup geographical details instantly using the POTA API.</p>
                        <a href="login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Login to Start</a>
                    </div>
                </div>
            </div>
            <!-- Default Slide 3 -->
            <div class="slide" style="background-image: url('uploads/slider/default3.jpg');">
                <div class="slide-overlay">
                    <div class="slide-content">
                        <h1 class="slide-title">Harmonious Dashboard Statistics</h1>
                        <p class="slide-desc">Explore visual breakdowns of bands, modes, and active countries in our modern real-time tracking graphs.</p>
                        <a href="#dashboard" class="btn btn-secondary"><i class="fa-solid fa-chart-pie"></i> View Stats</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($slider_images as $index => $img): ?>
                <div class="slide <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= htmlspecialchars($img['image_path']) ?>');">
                    <div class="slide-overlay">
                        <div class="slide-content">
                            <h1 class="slide-title">POTA Activation Tracker</h1>
                            <p class="slide-desc">Explore beautiful park locations, technical equipment profiles, and activator reports.</p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="slider-controls">
        <button class="slider-btn prev" aria-label="Previous Slide"><i class="fa-solid fa-chevron-left"></i></button>
        <button class="slider-btn next" aria-label="Next Slide"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
</section>

<!-- 2. Dashboard Statistics & Charts -->
<section id="dashboard" class="container" style="margin-bottom: 50px;">
    <h2 style="margin-bottom: 20px;"><i class="fa-solid fa-chart-line" style="color: var(--accent-color);"></i> Performance Dashboard</h2>
    
    <div class="dashboard-grid">
        <!-- Stats Numbers Card -->
        <div class="dashboard-card">
            <h3 class="dashboard-card-title"><i class="fa-solid fa-circle-info" style="color: var(--accent-color);"></i> Key Metrics</h3>
            <div class="dashboard-stats">
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['total_activations'] ?></div>
                    <div class="stat-label">Total Activations</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['total_qsos'] ?></div>
                    <div class="stat-label">Total QSOs</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $stats['total_parks'] ?></div>
                    <div class="stat-label">Unique Parks</div>
                </div>
            </div>
            
            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-secondary);">
                <p><i class="fa-solid fa-quote-left" style="color: var(--accent-color); margin-right: 5px;"></i> POTA (Parks on the Air) program encourages portable amateur radio operations from designated state, national, and international parks, raising awareness of outdoor recreation and backup communications.</p>
            </div>
        </div>
        
        <!-- Charts Visualizer Card -->
        <div class="dashboard-card" style="display: flex; flex-direction: column;">
            <h3 class="dashboard-card-title"><i class="fa-solid fa-chart-pie" style="color: var(--accent-color);"></i> Operating Breakdown</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; align-items: center; flex-grow: 1;">
                <?php if ($stats['total_activations'] == 0): ?>
                    <p style="color: var(--text-muted); text-align: center; width: 100%;">No operating statistics available yet. Data will populate once activations are logged.</p>
                <?php else: ?>
                    <div class="chart-wrapper" style="max-width: 300px; height: 200px; margin: 0 auto;">
                        <canvas id="operatingChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- 3. Search and Filters -->
<section class="container search-filter-section">
    <form action="index.php" method="GET" class="filter-grid">
        <div class="form-group">
            <label for="search" class="form-label">Search Keywords</label>
            <input type="text" name="search" id="search" class="form-control" placeholder="Park name, reference, activator, radio..." value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <div class="form-group">
            <label for="band" class="form-label">Band</label>
            <select name="band" id="band" class="form-control">
                <option value="">-- All Bands --</option>
                <?php foreach ($available_bands as $band): ?>
                    <option value="<?= htmlspecialchars($band) ?>" <?= $filter_band === $band ? 'selected' : '' ?>><?= htmlspecialchars($band) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="mode" class="form-label">Mode</label>
            <select name="mode" id="mode" class="form-control">
                <option value="">-- All Modes --</option>
                <?php foreach ($available_modes as $mode): ?>
                    <option value="<?= htmlspecialchars($mode) ?>" <?= $filter_mode === $mode ? 'selected' : '' ?>><?= htmlspecialchars($mode) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="sort" class="form-label">Sort By</label>
            <select name="sort" id="sort" class="form-control">
                <option value="date_desc" <?= $sort_by === 'date_desc' ? 'selected' : '' ?>>Date (Newest)</option>
                <option value="date_asc" <?= $sort_by === 'date_asc' ? 'selected' : '' ?>>Date (Oldest)</option>
                <option value="qso_desc" <?= $sort_by === 'qso_desc' ? 'selected' : '' ?>>QSOs (Highest)</option>
                <option value="qso_asc" <?= $sort_by === 'qso_asc' ? 'selected' : '' ?>>QSOs (Lowest)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="group" class="form-label">Group Results By</label>
            <select name="group" id="group" class="form-control">
                <option value="none" <?= $group_by === 'none' ? 'selected' : '' ?>>No Grouping</option>
                <option value="location" <?= $group_by === 'location' ? 'selected' : '' ?>>Location Prefix</option>
                <option value="month" <?= $group_by === 'month' ? 'selected' : '' ?>>Month & Year</option>
                <option value="activator" <?= $group_by === 'activator' ? 'selected' : '' ?>>Activator Callsign</option>
            </select>
        </div>
        
        <div class="form-group" style="grid-column: span 1; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="flex-grow: 1; height: 48px;"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
            <?php if ($search !== '' || $filter_band !== '' || $filter_mode !== '' || $sort_by !== 'date_desc' || $group_by !== 'none'): ?>
                <a href="index.php" class="btn btn-secondary" style="height: 48px; display: inline-flex; align-items: center; justify-content: center;" title="Reset filters"><i class="fa-solid fa-rotate-left"></i></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- 4. Grouped Activations List -->
<section class="container" style="margin-bottom: 60px;">
    <?php if (empty($grouped_activations)): ?>
        <div class="segment" style="text-align: center; padding: 60px 20px;">
            <i class="fa-solid fa-tower-broadcast fa-4x" style="color: var(--text-muted); margin-bottom: 20px;"></i>
            <h3>No Activations Logged</h3>
            <p style="color: var(--text-secondary); max-width: 500px; margin: 10px auto 25px;">There are no activation logs matching your query. Be the first to register and log your field activity!</p>
            <?php if ($is_logged_in): ?>
                <a href="add_activation.php" class="btn btn-primary"><i class="fa-solid fa-circle-plus"></i> Add Activation Now</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Sign Up to Log</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_activations as $group_title => $group_list): ?>
            <?php if (empty($group_list)) continue; ?>
            <div class="group-section">
                <?php if ($group_by !== 'none'): ?>
                    <div class="group-header">
                        <h3 class="group-header-title">
                            <?php if ($group_by === 'location'): ?>
                                <i class="fa-solid fa-earth-americas" style="color: var(--accent-color);"></i> Region: <?= htmlspecialchars($group_title) ?>
                            <?php elseif ($group_by === 'month'): ?>
                                <i class="fa-solid fa-calendar-days" style="color: var(--accent-color);"></i> <?= htmlspecialchars($group_title) ?>
                            <?php elseif ($group_by === 'activator'): ?>
                                <i class="fa-solid fa-user-tag" style="color: var(--accent-color);"></i> Activator: <?= htmlspecialchars($group_title) ?>
                            <?php endif; ?>
                        </h3>
                        <span class="group-header-badge"><?= count($group_list) ?> activation(s)</span>
                    </div>
                <?php endif; ?>
                
                <div class="activations-grid">
                    <?php foreach ($group_list as $act): ?>
                        <?php
                        // Fetch first image if any
                        $img_stmt = $pdo->prepare("SELECT image_path FROM activation_images WHERE activation_id = ? LIMIT 1");
                        $img_stmt->execute([$act['id']]);
                        $cover_img = $img_stmt->fetchColumn() ?: 'uploads/slider/default1.jpg';
                        ?>
                        <div class="activation-card">
                            <div class="card-header-img">
                                <img src="<?= htmlspecialchars($cover_img) ?>" alt="Cover Image" onerror="this.src='uploads/slider/default1.jpg';">
                                <span class="card-tag"><?= htmlspecialchars($act['park_reference']) ?></span>
                            </div>
                            <div class="card-body">
                                <h3 class="card-park-name" title="<?= htmlspecialchars($act['park_name']) ?>"><?= htmlspecialchars($act['park_name']) ?></h3>
                                
                                <div class="card-meta">
                                    <span class="card-meta-item"><i class="fa-solid fa-calendar"></i> <?= date('d M Y', strtotime($act['activation_date'])) ?></span>
                                    <span class="card-meta-item"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($act['username']) ?></span>
                                </div>
                                
                                <div class="card-details">
                                    <div class="detail-row">
                                        <span>QSO Count</span>
                                        <span><?= $act['qso_count'] ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Bands</span>
                                        <span title="<?= htmlspecialchars($act['bands']) ?>" style="display:block; max-width: 120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($act['bands']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Modes</span>
                                        <span title="<?= htmlspecialchars($act['modes']) ?>" style="display:block; max-width: 120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($act['modes']) ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span>Power Output</span>
                                        <span><?= $act['power_watts'] ? $act['power_watts'] . 'W' : 'N/A' ?></span>
                                    </div>
                                </div>
                                
                                <div class="card-footer-btns">
                                    <a href="activation_details.php?id=<?= $act['id'] ?>" class="btn btn-secondary btn-full"><i class="fa-solid fa-circle-info"></i> Details</a>
                                    <?php if ($is_logged_in && ($act['user_id'] == $user_id || $user_role === 'admin')): ?>
                                        <a href="edit_activation.php?id=<?= $act['id'] ?>" class="btn btn-primary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- 5. Chart.js Visualizations Script -->
<?php if ($stats['total_activations'] > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('operatingChart');
            if (ctx) {
                // We show modes statistics in a beautiful Pie/Doughnut chart
                const modeLabels = <?= json_encode(array_keys($mode_chart_data)) ?>;
                const modeValues = <?= json_encode(array_values($mode_chart_data)) ?>;
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: modeLabels,
                        datasets: [{
                            data: modeValues,
                            backgroundColor: [
                                '#2d6a4f', // Pine Green
                                '#d97706', // Earthy Amber
                                '#3a86c8', // Lake Blue
                                '#8c7853', // Earthy Sand
                                '#52b788', // Moss Green
                                '#788c80'  // Sage Grey
                            ],
                            borderWidth: 1,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: window.innerWidth < 480 ? 'bottom' : 'right',
                                labels: {
                                    color: '#4a5d51',
                                    font: {
                                        family: 'Inter',
                                        size: 11
                                    }
                                }
                            }
                        },
                        onResize: function(chart) {
                            const newPos = window.innerWidth < 480 ? 'bottom' : 'right';
                            if (chart.options.plugins.legend.position !== newPos) {
                                chart.options.plugins.legend.position = newPos;
                                chart.update();
                            }
                        }
                    }
                });
            }
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>