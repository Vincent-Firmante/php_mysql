<?php
require __DIR__ . '/config.php';

$p = ['name' => '', 'role' => '', 'interests' => '', 'avatar_color' => '#3b6cf6', 'photo_mime' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$p, $errors] = validate_profile($_POST);
    [$photoData, $photoMime, $photoError] = validate_profile_photo($_FILES['photo'] ?? []);
    if ($photoError) $errors['photo'] = $photoError;
    if (!$errors) {
        $stmt = $db->prepare('INSERT INTO profiles (name, role, interests, avatar_color, photo, photo_mime) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $p['name'], $p['role'], $p['interests'], $p['avatar_color'], $photoData, $photoMime);
        $stmt->execute();
        flash('Card created. Download it or share the link below.');
        redirect('read.php?id=' . $db->insert_id);
    }
    if (!preg_match('/^#[0-9a-f]{6}$/', $p['avatar_color'])) $p['avatar_color'] = '#3b6cf6';
}

page_header('New card');
echo '<h1 class="fw-bold mb-4">Make your card</h1>';
render_form($p, $errors, 'create.php', 'Save card');
page_footer();
