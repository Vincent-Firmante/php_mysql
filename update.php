<?php
require __DIR__ . '/config.php';

$id = filter_var($_GET['id'] ?? $_POST['id'] ?? '', FILTER_VALIDATE_INT);
$row = null;
if ($id) {
    $stmt = $db->prepare('SELECT id, name, role, interests, avatar_color, photo_mime FROM profiles WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
}
if (!$row) {
    flash('That card was not found. It may have been deleted.');
    redirect('read.php');
}

$p = $row;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$p, $errors] = validate_profile($_POST);
    $p['id'] = $id;
    $p['photo_mime'] = $row['photo_mime'];
    [$photoData, $photoMime, $photoError] = validate_profile_photo($_FILES['photo'] ?? []);
    if ($photoError) $errors['photo'] = $photoError;
    if (!$errors) {
        if ($photoData !== null) {
            $stmt = $db->prepare('UPDATE profiles SET name = ?, role = ?, interests = ?, avatar_color = ?, photo = ?, photo_mime = ? WHERE id = ?');
            $stmt->bind_param('ssssssi', $p['name'], $p['role'], $p['interests'], $p['avatar_color'], $photoData, $photoMime, $id);
        } else {
            $stmt = $db->prepare('UPDATE profiles SET name = ?, role = ?, interests = ?, avatar_color = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $p['name'], $p['role'], $p['interests'], $p['avatar_color'], $id);
        }
        $stmt->execute();
        flash('Card updated.');
        redirect('read.php?id=' . $id);
    }
    $p['id'] = $id;
    $p['photo_mime'] = $row['photo_mime'];
    if (!preg_match('/^#[0-9a-f]{6}$/', $p['avatar_color'])) $p['avatar_color'] = $row['avatar_color'];
}

page_header('Edit card');
echo '<h1 class="fw-bold mb-4">Edit card</h1>';
render_form($p, $errors, 'update.php?id=' . $id, 'Save changes');
page_footer();
