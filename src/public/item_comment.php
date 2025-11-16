<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$content = trim($_POST['content'] ?? '');

$error = '';

if ($itemId <= 0 || $content === '') {
    $error = 'Item and comment text are required.';
}

if ($error === '') {
    $stmt = $pdo->prepare('
        INSERT INTO comments (item_id, author_id, content, created_on)
        VALUES (:item_id, :author_id, :content, NOW())
    ');
    $stmt->execute([
        'item_id'   => $itemId,
        'author_id' => $user['id'],
        'content'   => $content,
    ]);

    $createdOn = date('Y-m-d H:i:s');
    $commentHtml = sprintf(
        '<strong>%s</strong><span class="cfp-muted" style="font-size:0.75rem;"> · %s</span><br><span>%s</span>',
        e($user['name']),
        e($createdOn),
        nl2br(e($content))
    );
}

// Always respond with JSON for this endpoint
header('Content-Type: application/json');

if ($error !== '') {
    echo json_encode(['success' => false, 'error' => $error]);
} else {
    echo json_encode(['success' => true, 'comment_html' => $commentHtml]);
}


