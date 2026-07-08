<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/static_page.php';

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url_base = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path, '/');
$siteName = $site_settings['site_name'] ?? 'Portal';

$contactTitle = trim($site_settings['contact_page_title'] ?? '') ?: 'Contact Us';
$contactSubtitle = trim($site_settings['contact_page_subtitle'] ?? '') ?: "Have a question? We'd love to hear from you.";
$contactContent = trim($site_settings['contact_page_content'] ?? '');

$custom_meta_title = trim($site_settings['contact_meta_title'] ?? '') ?: ('Contact Us - ' . $siteName);
$custom_meta_description = trim($site_settings['contact_meta_description'] ?? '') ?: ('Contact ' . $siteName . ' for questions, feedback, or support.');
$canonical_url = $site_url_base . '/contact/';

$contactItems = static_page_contact_items($site_settings);
$socialLinks = static_page_social_links($site_settings);

$formMsg = '';
$formType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    csrf_verify();
    $c_name    = trim($_POST['name'] ?? '');
    $c_email   = trim($_POST['email'] ?? '');
    $c_subject = trim($_POST['subject'] ?? '');
    $c_message = trim($_POST['message'] ?? '');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($c_name === '' || $c_email === '' || $c_subject === '' || $c_message === '') {
        $formType = 'err';
        $formMsg = 'Please fill in all fields.';
    } elseif (!filter_var($c_email, FILTER_VALIDATE_EMAIL)) {
        $formType = 'err';
        $formMsg = 'Please enter a valid email address.';
    } else {
        $chk = $conn->prepare("SELECT id FROM contact_queries WHERE ip_address = ? AND DATE(created_at) = CURDATE() LIMIT 1");
        $chk->bind_param('s', $ip);
        $chk->execute();
        $chkRes = $chk->get_result();
        if ($chkRes && $chkRes->num_rows > 0) {
            $formType = 'warn';
            $formMsg = 'You can only submit one message per day. We will get back to you soon.';
        } else {
            $stmt = $conn->prepare("INSERT INTO contact_queries (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $c_name, $c_email, $c_subject, $c_message, $ip);
            if ($stmt->execute()) {
                $formType = 'ok';
                $formMsg = 'Thank you! Your message has been sent successfully.';
                $_POST = [];
            } else {
                $formType = 'err';
                $formMsg = 'Something went wrong. Please try again later.';
            }
            $stmt->close();
        }
        $chk->close();
    }
}

include 'includes/header.php';
?>

<section class="static-hero">
    <div class="global-container static-hero__inner">
        <h1 class="static-hero__title"><?php echo htmlspecialchars($contactTitle); ?></h1>
        <p class="static-hero__subtitle"><?php echo htmlspecialchars($contactSubtitle); ?></p>
    </div>
</section>

<section class="global-container static-body">
    <div class="contact-grid">
        <aside class="static-card contact-aside">
            <h2 class="contact-aside__title">Get in Touch</h2>
            <p class="contact-aside__text">Reach us through any of the channels below. We usually respond within 24–48 hours.</p>

            <?php if (!empty($contactItems)): ?>
            <ul class="contact-info-list">
                <?php foreach ($contactItems as $item): ?>
                <li>
                    <span class="contact-info-icon"><i class="<?php echo htmlspecialchars($item['icon']); ?>"></i></span>
                    <div>
                        <span class="contact-info-label"><?php echo htmlspecialchars($item['label']); ?></span>
                        <a href="<?php echo htmlspecialchars($item['href']); ?>" class="contact-info-value"><?php echo htmlspecialchars($item['value']); ?></a>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if (!empty($socialLinks)): ?>
            <div class="contact-social">
                <span class="contact-social__label">Follow Us</span>
                <div class="contact-social__links">
                    <?php foreach ($socialLinks as $soc): ?>
                    <a href="<?php echo htmlspecialchars($soc['url']); ?>" target="_blank" rel="noopener noreferrer" class="contact-social-btn <?php echo htmlspecialchars($soc['class']); ?>" title="<?php echo htmlspecialchars($soc['label']); ?>">
                        <i class="<?php echo htmlspecialchars($soc['icon']); ?>"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>

        <div class="static-card contact-form-card">
            <?php if ($formMsg !== ''): ?>
            <div class="static-alert static-alert--<?php echo htmlspecialchars($formType); ?>">
                <i class="fa-solid <?php echo $formType === 'ok' ? 'fa-circle-check' : ($formType === 'warn' ? 'fa-circle-exclamation' : 'fa-triangle-exclamation'); ?>"></i>
                <?php echo htmlspecialchars($formMsg); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="contact-form">
                <?php echo csrf_field(); ?>
                <div class="contact-form-grid">
                    <div>
                        <label for="cName">Full Name</label>
                        <input type="text" id="cName" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Your name">
                    </div>
                    <div>
                        <label for="cEmail">Email Address</label>
                        <input type="email" id="cEmail" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="you@example.com">
                    </div>
                </div>
                <div class="contact-form-field">
                    <label for="cSubject">Subject</label>
                    <input type="text" id="cSubject" name="subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" placeholder="How can we help?">
                </div>
                <div class="contact-form-field">
                    <label for="cMessage">Message</label>
                    <textarea id="cMessage" name="message" rows="5" required placeholder="Write your message here..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="contact_submit" value="1" class="btn-brand contact-submit">
                    <i class="fa-solid fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </div>

    <?php if ($contactContent !== ''): ?>
    <div class="static-card static-card--wide contact-extra">
        <div class="static-prose"><?php echo $contactContent; ?></div>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
