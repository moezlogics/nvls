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
    <style>:root { --cs-primary: <?php echo htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'); ?>; }</style>
</head>
<body class="cs-body">
    <div class="cs-wrap">
        <div class="cs-card">
            <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $siteName; ?>" class="cs-logo">
            <?php else: ?>
            <div class="cs-brand"><?php echo $siteName; ?></div>
            <?php endif; ?>

            <div class="cs-icon" aria-hidden="true"><i class="fa-solid <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></div>
            <p class="cs-badge"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
            <h1 class="cs-title"><?php echo htmlspecialchars($headline, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="cs-sub"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>

            <div class="cs-progress" aria-hidden="true"><span></span></div>

            <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="cs-home">
                <i class="fa-solid fa-arrow-left"></i> Back to <?php echo $siteName; ?>
            </a>
        </div>
        <p class="cs-foot">HTTP 503 — Service temporarily unavailable</p>
    </div>
</body>
</html>
