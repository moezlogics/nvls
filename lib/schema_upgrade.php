<?php
if (!defined('PORTAL_APP')) {
    return;
}

function schema_upgrade(mysqli $conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $settingsCols = [
        'about_page_title'       => "VARCHAR(255) DEFAULT NULL",
        'about_page_subtitle'    => "VARCHAR(500) DEFAULT NULL",
        'about_meta_title'       => "VARCHAR(255) DEFAULT NULL",
        'about_meta_description' => "TEXT DEFAULT NULL",
        'about_page_content'     => "LONGTEXT DEFAULT NULL",
        'contact_page_title'       => "VARCHAR(255) DEFAULT NULL",
        'contact_page_subtitle'    => "VARCHAR(500) DEFAULT NULL",
        'contact_meta_title'       => "VARCHAR(255) DEFAULT NULL",
        'contact_meta_description' => "TEXT DEFAULT NULL",
        'contact_page_content'     => "LONGTEXT DEFAULT NULL",
        'community_coming_soon'    => "TINYINT(1) NOT NULL DEFAULT 0",
        'shop_coming_soon'         => "TINYINT(1) NOT NULL DEFAULT 0",
        'community_preview_key'    => "VARCHAR(64) DEFAULT NULL",
        'shop_preview_key'         => "VARCHAR(64) DEFAULT NULL",
    ];

    foreach ($settingsCols as $col => $def) {
        $chk = $conn->query("SHOW COLUMNS FROM settings LIKE '$col'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE settings ADD COLUMN $col $def");
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS contact_queries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        status ENUM('new','read','archived') NOT NULL DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        google_id VARCHAR(64) DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        profile_pic VARCHAR(500) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        status ENUM('active','suspended') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_username (username),
        UNIQUE KEY uq_email (email),
        UNIQUE KEY uq_google (google_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS community_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        category_id INT DEFAULT NULL,
        content TEXT NOT NULL,
        images LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_member (member_id),
        INDEX idx_category (category_id),
        INDEX idx_created (created_at),
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS community_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_like (member_id, post_id),
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS community_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        member_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post (post_id),
        FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE,
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS member_interests (
        member_id INT NOT NULL,
        category_id INT NOT NULL,
        weight INT NOT NULL DEFAULT 1,
        PRIMARY KEY (member_id, category_id),
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
