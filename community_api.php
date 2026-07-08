<?php
/**
 * community_api.php — Social feed logic and actions (create, like, comment) with algorithmic feed sorting.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/coming_soon.php';
coming_soon_gate('community', true);
require_once __DIR__ . '/lib/content_brain.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$memberId = $_SESSION['member_id'] ?? null;

// Helpers to update category affinity
function incrementAffinity($conn, $memberId, $categoryId) {
    if (!$memberId || !$categoryId) return;
    $stmt = $conn->prepare("INSERT INTO member_interests (member_id, category_id, weight) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE weight = weight + 1");
    $stmt->bind_param("ii", $memberId, $categoryId);
    $stmt->execute();
    $stmt->close();
}

function comm_normalize_post(array $post, $site_url) {
    $post['id'] = (int)$post['id'];
    $post['member_id'] = (int)$post['member_id'];
    $post['category_id'] = $post['category_id'] !== null ? (int)$post['category_id'] : null;
    $post['likes_count'] = (int)$post['likes_count'];
    $post['comments_count'] = (int)$post['comments_count'];
    $post['user_liked'] = (bool)$post['user_liked'];
    $post['images'] = $post['images'] ? json_decode($post['images'], true) : [];
    $avatar = $post['author_avatar'];
    if ($avatar && !preg_match("~^(?:f|ht)tps?://~i", $avatar)) {
        $post['author_avatar'] = $site_url . '/' . ltrim($avatar, '/');
    } elseif (!$avatar) {
        $post['author_avatar'] = $site_url . '/uploads/community/default_avatar.png';
    }
    return $post;
}

function comm_post_select_sql() {
    return "
        SELECT 
            cp.id, cp.member_id, cp.category_id, cp.content, cp.images, cp.created_at,
            m.name AS author_name, m.username AS author_username, m.profile_pic AS author_avatar,
            c.name AS category_name,
            (SELECT COUNT(*) FROM community_likes WHERE post_id = cp.id) AS likes_count,
            (SELECT COUNT(*) FROM community_comments WHERE post_id = cp.id) AS comments_count,
            EXISTS(SELECT 1 FROM community_likes WHERE post_id = cp.id AND member_id = ?) AS user_liked
        FROM community_posts cp
        JOIN members m ON cp.member_id = m.id
        LEFT JOIN categories c ON cp.category_id = c.id
    ";
}

if ($action === 'get_posts') {
    header('Content-Type: application/json; charset=utf-8');

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 10;
    $sort = ($_GET['sort'] ?? 'for_you') === 'latest' ? 'latest' : 'for_you';
    $categoryId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $likedMemberId = $memberId ? (int)$memberId : 0;
    $offset = ($page - 1) * $limit;

    $catWhere = $categoryId > 0 ? ' WHERE cp.category_id = ' . $categoryId : '';

    if ($sort === 'latest') {
        $countRes = $conn->query("SELECT COUNT(*) AS c FROM community_posts cp" . $catWhere);
        $totalPosts = $countRes ? (int)$countRes->fetch_assoc()['c'] : 0;

        $sql = comm_post_select_sql() . $catWhere . " ORDER BY cp.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'error' => 'Feed query failed.']);
            exit;
        }
        $stmt->bind_param('iii', $likedMemberId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $posts = [];
        while ($row = $result->fetch_assoc()) {
            $posts[] = comm_normalize_post($row, $site_url);
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'posts' => $posts,
            'has_more' => ($offset + $limit) < $totalPosts,
        ]);
        exit;
    }

    // For You — algorithmic ranking
    $affinities = [];
    if ($memberId) {
        $affRes = $conn->query("SELECT category_id, weight FROM member_interests WHERE member_id = " . (int)$memberId);
        if ($affRes) {
            while ($row = $affRes->fetch_assoc()) {
                $affinities[(int)$row['category_id']] = (int)$row['weight'];
            }
        }
    } else {
        $cookieData = $_COOKIE['guest_category_affinity'] ?? '';
        if ($cookieData !== '') {
            $decoded = json_decode($cookieData, true);
            if (is_array($decoded)) {
                foreach ($decoded as $cid => $w) {
                    $affinities[(int)$cid] = (int)$w;
                }
            }
        }
    }

    $sql = comm_post_select_sql() . $catWhere . " ORDER BY cp.created_at DESC LIMIT 300";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'error' => 'Feed query failed.']);
        exit;
    }
    $stmt->bind_param('i', $likedMemberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    $now = time();

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['feed_seed'])) {
            $_SESSION['feed_seed'] = rand(1, 1000000);
        }
        $seed = (int)$_SESSION['feed_seed'];
    } else {
        $seed = (int)($_COOKIE['comm_feed_seed'] ?? 0);
        if ($seed === 0) {
            $seed = rand(1, 1000000);
            setcookie('comm_feed_seed', (string)$seed, time() + 2592000, '/');
        }
    }

    while ($post = $result->fetch_assoc()) {
        $post = comm_normalize_post($post, $site_url);
        $ageHours = max(0.1, ($now - strtotime($post['created_at'])) / 3600);
        $recencyDecay = 1.0 / (1.0 + pow($ageHours, 1.2));
        $engagement = 1.0 + ($post['likes_count'] * 2.5) + ($post['comments_count'] * 6.0);
        $affinityBoost = 1.0;
        if ($post['category_id'] && isset($affinities[$post['category_id']])) {
            $affinityBoost = 1.0 + (0.35 * min(20, $affinities[$post['category_id']]));
        }
        $ownBoost = ($memberId && (int)$post['member_id'] === (int)$memberId) ? 0.85 : 1.0;
        $randFactor = 0.88 + 0.24 * abs(sin($post['id'] + $seed));
        $post['ranking_score'] = $engagement * $recencyDecay * $affinityBoost * $randFactor * $ownBoost;
        $posts[] = $post;
    }
    $stmt->close();

    usort($posts, function ($a, $b) {
        return $b['ranking_score'] <=> $a['ranking_score'];
    });

    $totalPosts = count($posts);
    $sliced = array_slice($posts, $offset, $limit);

    echo json_encode([
        'status' => 'success',
        'posts' => $sliced,
        'has_more' => ($offset + $limit) < $totalPosts,
    ]);
    exit;
}

if ($action === 'get_comments') {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            cc.id, cc.comment, cc.created_at,
            m.name AS author_name, m.username AS author_username, m.profile_pic AS author_avatar
        FROM community_comments cc
        JOIN members m ON cc.member_id = m.id
        WHERE cc.post_id = ?
        ORDER BY cc.id ASC
    ");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $res = $stmt->get_result();
    $comments = [];
    while ($row = $res->fetch_assoc()) {
        $avatar = $row['author_avatar'];
        if ($avatar && !preg_match("~^(?:f|ht)tps?://~i", $avatar)) {
            $row['author_avatar'] = $site_url . '/' . ltrim($avatar, '/');
        } elseif (!$avatar) {
            $row['author_avatar'] = $site_url . '/uploads/community/default_avatar.png';
        }
        $comments[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'comments' => $comments
    ]);
    exit;
}

// All actions below require active member session
if (!$memberId) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in with Google to interact in the community.']);
    exit;
}

if ($action === 'create_post') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $content = trim($_POST['content'] ?? '');
    $categoryId = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (empty($content)) {
        http_response_code(400);
        echo json_encode(['error' => 'Post content cannot be empty']);
        exit;
    }

    $imagePaths = [];
    $absPaths = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/uploads/community/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $filesCount = count($_FILES['images']['name']);
        for ($i = 0; $i < min(4, $filesCount); $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $filename = uniqid('post_', true) . '.' . $ext;
                    $targetPath = $uploadDir . $filename;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetPath)) {
                        $imagePaths[] = 'uploads/community/' . $filename;
                        $absPaths[] = $targetPath;
                    }
                }
            }
        }
    }

    $mod = content_brain_moderate('community_post', $content, $absPaths);
    if (!$mod['ok']) {
        foreach ($absPaths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        http_response_code(400);
        echo json_encode(['error' => $mod['reason']]);
        exit;
    }

    $imagesJson = !empty($imagePaths) ? json_encode($imagePaths) : null;

    $stmt = $conn->prepare("INSERT INTO community_posts (member_id, category_id, content, images) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $memberId, $categoryId, $content, $imagesJson);
    
    if ($stmt->execute()) {
        $postId = $stmt->insert_id;
        
        // Auto-increment category interest weight for the poster as well
        if ($categoryId) {
            incrementAffinity($conn, $memberId, $categoryId);
        }

        echo json_encode([
            'status' => 'success',
            'post' => [
                'id' => $postId,
                'content' => $content,
                'images' => $imagePaths,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create post']);
    }
    $stmt->close();
    exit;
}

if ($action === 'toggle_like') {
    $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }

    // Check if post exists and get its category
    $stmt = $conn->prepare("SELECT category_id FROM community_posts WHERE id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $res = $stmt->get_result();
    $post = $res->fetch_assoc();
    $stmt->close();

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    // Check if liked already
    $stmt = $conn->prepare("SELECT 1 FROM community_likes WHERE member_id = ? AND post_id = ?");
    $stmt->bind_param("ii", $memberId, $postId);
    $stmt->execute();
    $stmt->store_result();
    $alreadyLiked = $stmt->num_rows > 0;
    $stmt->close();

    if ($alreadyLiked) {
        $stmt = $conn->prepare("DELETE FROM community_likes WHERE member_id = ? AND post_id = ?");
        $stmt->bind_param("ii", $memberId, $postId);
        $stmt->execute();
        $stmt->close();
        $userLiked = false;
    } else {
        $stmt = $conn->prepare("INSERT INTO community_likes (member_id, post_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $memberId, $postId);
        $stmt->execute();
        $stmt->close();
        $userLiked = true;

        // Boost affinity on like action
        if ($post['category_id']) {
            incrementAffinity($conn, $memberId, $post['category_id']);
        }
    }

    // Count updated likes
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM community_likes WHERE post_id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $totalLikes = (int)$row['total'];
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'likes_count' => $totalLikes,
        'user_liked' => $userLiked
    ]);
    exit;
}

if ($action === 'create_comment') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    $comment = trim($input['comment'] ?? '');

    if ($postId <= 0 || empty($comment)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parameters']);
        exit;
    }

    $mod = content_brain_moderate('comment', $comment);
    if (!$mod['ok']) {
        http_response_code(400);
        echo json_encode(['error' => $mod['reason']]);
        exit;
    }

    // Check if post exists and get its category
    $stmt = $conn->prepare("SELECT category_id FROM community_posts WHERE id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO community_comments (post_id, member_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $postId, $memberId, $comment);
    
    if ($stmt->execute()) {
        $commentId = $stmt->insert_id;
        
        // Comment is a high-intent action: double boost affinity
        if ($post['category_id']) {
            incrementAffinity($conn, $memberId, $post['category_id']);
            incrementAffinity($conn, $memberId, $post['category_id']);
        }

        $avatar = $_SESSION['member_profile_pic'] ?? '';
        if ($avatar && !preg_match("~^(?:f|ht)tps?://~i", $avatar)) {
            $avatar = $site_url . '/' . ltrim($avatar, '/');
        } elseif (!$avatar) {
            $avatar = $site_url . '/uploads/community/default_avatar.png';
        }

        echo json_encode([
            'status' => 'success',
            'comment' => [
                'id' => $commentId,
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s'),
                'author_name' => $_SESSION['member_name'],
                'author_username' => $_SESSION['member_username'],
                'author_avatar' => $avatar
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add comment']);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_post') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM community_posts WHERE id = ? AND member_id = ?");
    $stmt->bind_param('ii', $postId, $memberId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$deleted) {
        http_response_code(403);
        echo json_encode(['error' => 'Post not found or you cannot delete it.']);
        exit;
    }
    echo json_encode(['status' => 'success']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
?>
