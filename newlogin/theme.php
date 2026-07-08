<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['admin_role'] != 'main_admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch current settings
$settings_query = $conn->query("SELECT theme_primary, theme_secondary, theme_hover FROM settings WHERE id = 1");
$site_settings = $settings_query->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $primary = $conn->real_escape_string($_POST['theme_primary']);
    $secondary = $conn->real_escape_string($_POST['theme_secondary']);
    $hover = $conn->real_escape_string($_POST['theme_hover']);

    $query = "UPDATE settings SET theme_primary='$primary', theme_secondary='$secondary', theme_hover='$hover' WHERE id=1";
    
    if ($conn->query($query) === TRUE) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Theme colors updated successfully!</div>";
        clear_page_cache();
        $site_settings['theme_primary'] = $primary;
        $site_settings['theme_secondary'] = $secondary;
        $site_settings['theme_hover'] = $hover;
    } else {
        error_log("Database error: " . $conn->error); $msg = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i> Something went wrong. Please try again.</div>";
    }
}

// 10 Professional Color Presets suitable for Portal sites
$presets = [
    [
        'name' => 'Classic Edu',
        'primary' => '#0A4275',
        'secondary' => '#F9A826',
        'hover' => '#fbbf24',
        'desc' => 'Traditional, trustworthy, academic.'
    ],
    [
        'name' => 'Academic Green',
        'primary' => '#2C5E3B',
        'secondary' => '#F4F1DE',
        'hover' => '#4CAF50',
        'desc' => 'Growth, focus, nature-inspired.'
    ],
    [
        'name' => 'Oxford Blue',
        'primary' => '#002147',
        'secondary' => '#E5E5E5',
        'hover' => '#003366',
        'desc' => 'Professional, highly formal, established.'
    ],
    [
        'name' => 'Modern Tech',
        'primary' => '#4A154B',
        'secondary' => '#36C5F0',
        'hover' => '#1DBA9F',
        'desc' => 'Innovative, forward-thinking, STEM focus.'
    ],
    [
        'name' => 'Crimson Scholar',
        'primary' => '#9E1B32',
        'secondary' => '#F8F9FA',
        'hover' => '#C41E3A',
        'desc' => 'Bold, energetic, university standard.'
    ],
    [
        'name' => 'Trust & Growth',
        'primary' => '#008080',
        'secondary' => '#FF7F50',
        'hover' => '#20B2AA',
        'desc' => 'Approachable, vibrant, encouraging.'
    ],
    [
        'name' => 'Elegant Ivory',
        'primary' => '#1D2D50',
        'secondary' => '#FFFFF0',
        'hover' => '#133B5C',
        'desc' => 'Premium, sleek, luxury education.'
    ],
    [
        'name' => 'Future Forward',
        'primary' => '#4B0082',
        'secondary' => '#00FFFF',
        'hover' => '#9370DB',
        'desc' => 'Global, tech-savvy, modern.'
    ],
    [
        'name' => 'Warm Autumn',
        'primary' => '#800020',
        'secondary' => '#FFDB58',
        'hover' => '#A52A2A',
        'desc' => 'Inviting, classical, rich.'
    ],
    [
        'name' => 'Clean Minimalist',
        'primary' => '#333333',
        'secondary' => '#007BFF',
        'hover' => '#555555',
        'desc' => 'Minimalist, clear, universal appeal.'
    ]
];

$current_primary = $site_settings['theme_primary'] ?? '#0f2c5c';
$current_secondary = $site_settings['theme_secondary'] ?? '#6CC832';
$current_hover = $site_settings['theme_hover'] ?? '#fbbf24';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme & Appearance - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .preset-card {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 2px solid transparent;
        }
        .preset-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
        }
        .preset-card.active {
            border-color: #6CC832;
            box-shadow: 0 0 0 0.25rem rgba(108,200,50, 0.25);
        }
        .color-box {
            width: 40px;
            height: 40px;
            border-radius:0;
            display: inline-block;
            border: 1px solid rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Theme & Appearance</h2>
        <p class="text-muted mb-4">Pick a preset or fine-tune your colors. Changes apply across the entire storefront (Buttons, Headers, Links, etc).</p>

        <?php if(isset($msg)) echo $msg; ?>

        <form action="" method="POST" id="themeForm">
                            <?php echo csrf_field(); ?>
            
            <div class="row mb-5">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white fw-bold py-3">Choose a Preset</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach($presets as $index => $preset): ?>
                                <div class="col-md-6 col-lg-4">
                                        <div class="card preset-card h-100 <?php echo ($current_primary == $preset['primary'] && $current_secondary == $preset['secondary']) ? 'active' : ''; ?>" onclick="selectPreset('<?php echo $preset['primary']; ?>', '<?php echo $preset['secondary']; ?>', '<?php echo $preset['hover']; ?>', this)">
                                        <div class="card-body">
                                            <div class="d-flex mb-3">
                                                <div class="color-box me-1" style="background-color: <?php echo $preset['primary']; ?>;" title="Primary"></div>
                                                <div class="color-box me-1" style="background-color: <?php echo $preset['secondary']; ?>;" title="Secondary"></div>
                                                <div class="color-box" style="background-color: <?php echo $preset['hover']; ?>;" title="Hover/Accent"></div>
                                            </div>
                                            <h6 class="fw-bold mb-1"><?php echo $preset['name']; ?></h6>
                                            <p class="text-muted small mb-0" style="line-height: 1.2;"><?php echo $preset['desc']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                        <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                            <span>Custom Colors</span>
                            <button type="submit" class="btn btn-primary btn-sm px-4">Save Theme</button>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Primary Color</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" name="theme_primary" id="primaryColorInput" class="form-control form-control-color border-0 p-0 shadow-sm" style="width: 50px; height: 50px;" value="<?php echo htmlspecialchars($current_primary); ?>">
                                    <input type="text" id="primaryText" class="form-control bg-light" value="<?php echo htmlspecialchars($current_primary); ?>" oninput="document.getElementById('primaryColorInput').value = this.value">
                                </div>
                                <small class="text-muted mt-2 d-block">Used for main buttons (e.g. View Details), links, and key highlights.</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Secondary Color</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" name="theme_secondary" id="secondaryColorInput" class="form-control form-control-color border-0 p-0 shadow-sm" style="width: 50px; height: 50px;" value="<?php echo htmlspecialchars($current_secondary); ?>">
                                    <input type="text" id="secondaryText" class="form-control bg-light" value="<?php echo htmlspecialchars($current_secondary); ?>" oninput="document.getElementById('secondaryColorInput').value = this.value">
                                </div>
                                <small class="text-muted mt-2 d-block">Used for badges, secondary buttons, or decorative elements.</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Hover / Accent Color</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" name="theme_hover" id="hoverColorInput" class="form-control form-control-color border-0 p-0 shadow-sm" style="width: 50px; height: 50px;" value="<?php echo htmlspecialchars($current_hover); ?>">
                                    <input type="text" id="hoverText" class="form-control bg-light" value="<?php echo htmlspecialchars($current_hover); ?>" oninput="document.getElementById('hoverColorInput').value = this.value">
                                </div>
                                <small class="text-muted mt-2 d-block">Used for mouse hover effects, active links, and prominent icons.</small>
                            </div>

                            <hr>
                            
                            <h6 class="fw-bold mb-3">Live Preview</h6>
                            <div class="p-4 rounded border" style="background: #f8f9fa;">
                                <h4 style="color: var(--bs-primary, #6CC832);" id="previewHeading">Beautiful Headings</h4>
                                <p class="text-muted small mb-3">Body copy will inherit your text color automatically.</p>
                                <button type="button" class="btn text-white w-100 mb-2" id="previewBtnPrimary" style="background-color: <?php echo htmlspecialchars($current_primary); ?>; border-color: <?php echo htmlspecialchars($current_primary); ?>;">Primary Button</button>
                                <button type="button" class="btn text-white w-100 mb-2" id="previewBtnHover" style="background-color: <?php echo htmlspecialchars($current_hover); ?>; border-color: <?php echo htmlspecialchars($current_hover); ?>;">Hovered Button</button>
                                <button type="button" class="btn w-100 bg-white" id="previewBtnOutline" style="color: <?php echo htmlspecialchars($current_primary); ?>; border: 1px solid <?php echo htmlspecialchars($current_primary); ?>;">Outline Button</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
        const primaryInput = document.getElementById('primaryColorInput');
        const secondaryInput = document.getElementById('secondaryColorInput');
        const hoverInput = document.getElementById('hoverColorInput');
        const primaryText = document.getElementById('primaryText');
        const secondaryText = document.getElementById('secondaryText');
        const hoverText = document.getElementById('hoverText');
        
        const previewHeading = document.getElementById('previewHeading');
        const previewBtnPrimary = document.getElementById('previewBtnPrimary');
        const previewBtnHover = document.getElementById('previewBtnHover');
        const previewBtnOutline = document.getElementById('previewBtnOutline');

        function updatePreview() {
            let pColor = primaryInput.value;
            let sColor = secondaryInput.value;
            let hColor = hoverInput.value;
            
            primaryText.value = pColor;
            secondaryText.value = sColor;
            hoverText.value = hColor;
            
            previewHeading.style.color = pColor;
            previewBtnPrimary.style.backgroundColor = pColor;
            previewBtnPrimary.style.borderColor = pColor;
            
            previewBtnHover.style.backgroundColor = hColor;
            previewBtnHover.style.borderColor = hColor;

            previewBtnOutline.style.color = pColor;
            previewBtnOutline.style.borderColor = pColor;
        }

        primaryInput.addEventListener('input', updatePreview);
        secondaryInput.addEventListener('input', updatePreview);
        hoverInput.addEventListener('input', updatePreview);

        function selectPreset(primary, secondary, hover, element) {
            primaryInput.value = primary;
            secondaryInput.value = secondary;
            hoverInput.value = hover;
            updatePreview();
            
            // Remove active class from all cards
            document.querySelectorAll('.preset-card').forEach(card => card.classList.remove('active'));
            // Add to clicked
            element.classList.add('active');
        }
    </script>
</body>
</html>
