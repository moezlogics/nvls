-- Database Name: novels_db
CREATE DATABASE IF NOT EXISTS novels_db;
USE novels_db;

-- 1. admin_users table (matches the active schema)
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    bio TEXT DEFAULT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    role ENUM('main_admin', 'author') DEFAULT 'author',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (username: admin, password: password123)
-- This matches the standard bcrypt hash
INSERT INTO admin_users (username, email, password, role) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'main_admin')
ON DUPLICATE KEY UPDATE username=username;

-- 2. categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description LONGTEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description TEXT DEFAULT NULL
);

-- Insert default categories
INSERT INTO categories (name, slug) VALUES 
('General', 'general'), 
('News', 'news'), 
('Reviews', 'reviews'), 
('Stories', 'stories')
ON DUPLICATE KEY UPDATE name=name;

-- 2b. writers table
CREATE TABLE IF NOT EXISTS writers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    bio LONGTEXT DEFAULT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description TEXT DEFAULT NULL
);

-- 3. posts table (Novel modified)
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category_id INT,
    additional_categories VARCHAR(255) DEFAULT '',
    writer_id INT,
    description LONGTEXT,
    image VARCHAR(255) DEFAULT NULL,
    read_online_url VARCHAR(512) DEFAULT NULL,
    download_url VARCHAR(512) DEFAULT NULL,
    file_size VARCHAR(100) DEFAULT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    pages INT DEFAULT NULL,
    posted_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'draft') DEFAULT 'active',
    is_featured TINYINT(1) DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (writer_id) REFERENCES writers(id) ON DELETE SET NULL
);

-- 4. settings table (Platform Settings)
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY,
    site_name VARCHAR(100) DEFAULT 'Kitab Nagri',
    tagline VARCHAR(255) DEFAULT 'Read Online & Download Urdu Novels',
    site_logo VARCHAR(255) DEFAULT NULL,
    site_favicon VARCHAR(255) DEFAULT NULL,
    contact_email VARCHAR(100) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    contact_whatsapp VARCHAR(50) DEFAULT NULL,
    facebook_url VARCHAR(255) DEFAULT NULL,
    instagram_url VARCHAR(255) DEFAULT NULL,
    tiktok_url VARCHAR(255) DEFAULT NULL,
    youtube_url VARCHAR(255) DEFAULT NULL,
    seo_description TEXT DEFAULT NULL,
    seo_keywords TEXT DEFAULT NULL,
    homepage_title VARCHAR(255) DEFAULT NULL,
    homepage_description TEXT DEFAULT NULL,
    permalink_structure VARCHAR(50) DEFAULT 'plain',
    maintenance_mode TINYINT(1) DEFAULT 0,
    google_analytics TEXT DEFAULT NULL,
    search_console TEXT DEFAULT NULL,
    larapush_code TEXT DEFAULT NULL,
    theme_primary VARCHAR(20) DEFAULT '#0f2c5c',
    theme_secondary VARCHAR(20) DEFAULT '#1a56db',
    theme_hover VARCHAR(20) DEFAULT '#fbbf24',
    footer_col1 TEXT DEFAULT NULL,
    footer_col2 TEXT DEFAULT NULL,
    footer_col3 TEXT DEFAULT NULL
);

-- Insert default settings if not exists
INSERT IGNORE INTO settings (id) VALUES (1);

-- 5. pages table (Static Pages like About Us, Contact)
CREATE TABLE IF NOT EXISTS pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT,
    status ENUM('publish', 'draft', 'trash') DEFAULT 'publish',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 6. comments table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    member_id INT NULL DEFAULT NULL,
    parent_id INT NULL DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    message TEXT NOT NULL,
    status ENUM('pending', 'approved') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- 7. login_attempts table
CREATE TABLE IF NOT EXISTS login_attempts (
    ip_address VARCHAR(45) PRIMARY KEY,
    attempts INT DEFAULT 0,
    last_attempt_time INT DEFAULT 0
);

-- 8. media table
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    alt_text VARCHAR(300) DEFAULT NULL,
    caption VARCHAR(300) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    mime VARCHAR(100) DEFAULT NULL,
    filesize INT DEFAULT NULL,
    width INT DEFAULT NULL,
    height INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. menu_items table
CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    parent_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0
);

