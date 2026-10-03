<?php
require __DIR__ . '/config.php';

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    exit;
}

$stmt = $db->prepare('SELECT photo, photo_mime FROM profiles WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!$photo || $photo['photo'] === null || !in_array($photo['photo_mime'], $allowed, true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $photo['photo_mime']);
header('Content-Length: ' . strlen($photo['photo']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
echo $photo['photo'];