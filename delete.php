<?php
require __DIR__ . '/config.php';

// Deleting only works through a POST form (with CSRF token), never a plain link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('read.php');
csrf_check();

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if ($id) {
    $stmt = $db->prepare('DELETE FROM profiles WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    flash($stmt->affected_rows ? 'Card deleted.' : 'That card was already gone.');
} else {
    flash('Invalid card.');
}
redirect('read.php');
