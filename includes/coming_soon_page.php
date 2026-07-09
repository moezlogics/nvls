<?php
/** @var string $siteName $primary $logoUrl $title $headline $subtitle $icon $homeUrl $cssUrl $isCommunity */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> — Coming Soon | <?php echo $siteName; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <style>:root { --cs-accent: <?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>; }</style>
</head>
<body>
    <div class="cs">
        <div class="cs-panel">
            <div class="cs-accent" aria-hidden="true"></div>
            
            <p class="cs-section"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
            <h1><?php echo htmlspecialchars($headline, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="cs-desc"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>

            <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cs-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to <?php echo $siteName; ?>
            </a>
        </div>
    </div>
</body>
</html>
