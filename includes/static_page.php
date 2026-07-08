<?php
function static_page_social_links(array $s) {
    $links = [];
    if (!empty($s['facebook_url']))  $links[] = ['icon' => 'fa-brands fa-facebook-f', 'label' => 'Facebook',  'url' => $s['facebook_url'],  'class' => 'sp-soc-fb'];
    if (!empty($s['instagram_url'])) $links[] = ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => $s['instagram_url'], 'class' => 'sp-soc-ig'];
    if (!empty($s['tiktok_url']))    $links[] = ['icon' => 'fa-brands fa-tiktok',    'label' => 'TikTok',    'url' => $s['tiktok_url'],    'class' => 'sp-soc-tt'];
    if (!empty($s['youtube_url']))   $links[] = ['icon' => 'fa-brands fa-youtube',   'label' => 'YouTube',   'url' => $s['youtube_url'],   'class' => 'sp-soc-yt'];
    return $links;
}

function static_page_whatsapp_url($number) {
    $digits = preg_replace('/\D+/', '', (string)$number);
    return $digits !== '' ? 'https://wa.me/' . $digits : '';
}

function static_page_contact_items(array $s) {
    $items = [];
    if (!empty($s['contact_email'])) {
        $items[] = ['icon' => 'fa-regular fa-envelope', 'label' => 'Email', 'value' => $s['contact_email'], 'href' => 'mailto:' . $s['contact_email']];
    }
    if (!empty($s['contact_phone'])) {
        $items[] = ['icon' => 'fa-solid fa-phone', 'label' => 'Phone', 'value' => $s['contact_phone'], 'href' => 'tel:' . preg_replace('/\s+/', '', $s['contact_phone'])];
    }
    if (!empty($s['contact_whatsapp'])) {
        $wa = static_page_whatsapp_url($s['contact_whatsapp']);
        if ($wa) {
            $items[] = ['icon' => 'fa-brands fa-whatsapp', 'label' => 'WhatsApp', 'value' => $s['contact_whatsapp'], 'href' => $wa];
        }
    }
    return $items;
}
