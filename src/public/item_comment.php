<?php
// Author: Sem Axil Rais (40113324)

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

cfp_require_login();

$pdo = cfp_get_pdo();
$user = cfp_current_user();

$itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$content = trim($_POST['content'] ?? '');
$parentCommentId = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : 0;

$error = '';

if ($itemId <= 0 || $content === '') {
    $error = 'Item and comment text are required.';
}

if ($error === '') {
    // Look up item author to treat authors specially for comment rules.
    $itemStmt = $pdo->prepare('SELECT author_id FROM items WHERE id = :id');
    $itemStmt->execute(['id' => $itemId]);
    $itemRow = $itemStmt->fetch();

    if (!$itemRow) {
        $error = 'Item not found.';
    } else {
        $isItemAuthor = ((int)$itemRow['author_id'] === (int)$user['id']);

        // Enforce rule: only members who have downloaded the item can comment,
        // except the item author who may always comment/reply.
        if (!$isItemAuthor) {
            $downloadCheck = $pdo->prepare('
                SELECT COUNT(*) FROM downloads
                WHERE member_id = :mid AND item_id = :item_id
            ');
            $downloadCheck->execute([
                'mid'     => $user['id'],
                'item_id' => $itemId,
            ]);
            $hasDownloaded = (int)$downloadCheck->fetchColumn() > 0;

            if (!$hasDownloaded) {
                $error = 'You must download this item before commenting.';
            }
        }

        // If this is a reply, validate that the parent comment belongs to the same item.
        if ($error === '' && $parentCommentId > 0) {
            $parentStmt = $pdo->prepare('SELECT item_id FROM comments WHERE id = :id');
            $parentStmt->execute(['id' => $parentCommentId]);
            $parent = $parentStmt->fetch();

            if (!$parent || (int)$parent['item_id'] !== $itemId) {
                $error = 'Invalid comment reply target.';
            }
        }
    }
}

if ($error === '') {
    $stmt = $pdo->prepare('
        INSERT INTO comments (item_id, author_id, parent_comment_id, content, created_on)
        VALUES (:item_id, :author_id, :parent_comment_id, :content, NOW())
    ');
    $stmt->execute([
        'item_id'          => $itemId,
        'author_id'        => $user['id'],
        'parent_comment_id'=> $parentCommentId > 0 ? $parentCommentId : null,
        'content'          => $content,
    ]);

    $commentId = (int)$pdo->lastInsertId();
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
    echo json_encode([
        'success'           => true,
        'comment_html'      => $commentHtml,
        'comment_id'        => $commentId,
        'parent_comment_id' => $parentCommentId > 0 ? $parentCommentId : null,
    ]);
}


