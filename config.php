<?php
// ---- Database settings (edit these to match your MySQL setup) ----
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'app_db';

session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $db->set_charset('utf8mb4');
  if ($db->query("SHOW COLUMNS FROM profiles LIKE 'photo'")->num_rows === 0) {
    $db->query('ALTER TABLE profiles ADD photo MEDIUMBLOB NULL, ADD photo_mime VARCHAR(32) NULL');
  }
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Database connection failed. Import app_db.sql and check the settings at the top of config.php.');
}

// ---- Helpers ----
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid or expired form. Go back, refresh the page and try again.');
    }
}
function flash(?string $msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}
function redirect(string $url): void { header("Location: $url"); exit; }

function initials(string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) $out .= mb_strtoupper(mb_substr($w, 0, 1));
    return $out !== '' ? $out : '?';
}

function interest_list(string $csv): array {
    $items = [];
    foreach (explode(',', $csv) as $t) {
        $t = trim(mb_substr(trim($t), 0, 24));
        if ($t !== '' && !in_array(mb_strtolower($t), array_map('mb_strtolower', $items), true)) $items[] = $t;
    }
    return array_slice($items, 0, 8);
}

  function validate_profile_photo(array $upload): array {
    if (!isset($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE) return [null, null, null];
    if ($upload['error'] !== UPLOAD_ERR_OK) return [null, null, 'The photo could not be uploaded. Try a smaller image.'];
    if (($upload['size'] ?? 0) > 5 * 1024 * 1024) return [null, null, 'The photo must be 5 MB or smaller.'];
    if (!is_uploaded_file($upload['tmp_name'] ?? '')) return [null, null, 'Choose a valid image file.'];

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $image = @getimagesize($upload['tmp_name']);
    $mime = $image['mime'] ?? '';
    if (!in_array($mime, $allowed, true)) {
      return [null, null, 'Use a JPG, PNG, or WebP image.'];
    }

    $data = file_get_contents($upload['tmp_name']);
    if ($data === false) return [null, null, 'The photo could not be read. Please try again.'];
    return [$data, $mime, null];
  }

// Validate form input. Returns [cleanData, errors]
function validate_profile(array $in): array {
    $d = [
        'name'         => trim($in['name'] ?? ''),
        'role'         => trim($in['role'] ?? ''),
        'interests'    => implode(', ', interest_list($in['interests'] ?? '')),
        'avatar_color' => strtolower(trim($in['avatar_color'] ?? '')),
    ];
    $err = [];
    if ($d['name'] === '') $err['name'] = 'Enter a name.';
    elseif (mb_strlen($d['name']) > 60) $err['name'] = 'Name must be 60 characters or fewer.';
    if ($d['role'] === '') $err['role'] = 'Enter a role.';
    elseif (mb_strlen($d['role']) > 60) $err['role'] = 'Role must be 60 characters or fewer.';
    if (!preg_match('/^#[0-9a-f]{6}$/', $d['avatar_color'])) $err['avatar_color'] = 'Pick a valid color.';
    return [$d, $err];
}

// ---- Layout ----
function page_header(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Cardmaker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@400;600;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
try { document.documentElement.dataset.theme = localStorage.getItem('cardmaker-theme') || 'light'; } catch (err) {}
</script>
<style>
:root {
  color-scheme:light; --ink:#142b4a; --paper:#edf2f8; --muted:#65758c; --surface:#fff; --border:#d4dfed; --soft:#e8eef6;
  --page-gradient:linear-gradient(135deg,#e6eef8 0%,#f7f9fc 52%,#dce7f4 100%);
  --surface-gradient:linear-gradient(145deg,#fff 0%,#f2f6fb 100%);
  --header-gradient:linear-gradient(105deg,#d3e2f3 0%,#e8f0fa 100%); --footer-gradient:linear-gradient(105deg,#dce8f5 0%,#cbdcf0 100%);
  --panel-shadow:0 8px 24px rgba(20,43,74,.08); --card-shadow:0 10px 30px rgba(20,43,74,.16); --card-hover-shadow:0 18px 38px rgba(20,43,74,.25);
}
:root[data-theme="dark"] {
  color-scheme:dark; --ink:#e4edf8; --paper:#0c192b; --muted:#a2b2c8; --surface:#14263d; --border:#2a405c; --soft:#203650;
  --page-gradient:linear-gradient(135deg,#0a1729 0%,#11243b 52%,#0b1b30 100%);
  --surface-gradient:linear-gradient(145deg,#182d47 0%,#112239 100%);
  --header-gradient:linear-gradient(105deg,#102944 0%,#183956 100%); --footer-gradient:linear-gradient(105deg,#0a1c31 0%,#132e49 100%);
  --panel-shadow:0 10px 28px rgba(0,0,0,.2); --card-shadow:0 12px 32px rgba(0,0,0,.32); --card-hover-shadow:0 20px 42px rgba(0,0,0,.48);
}
body { display:flex; flex-direction:column; font-family:'Bricolage Grotesque',system-ui,sans-serif; background:var(--page-gradient); background-attachment:fixed; color:var(--ink); min-height:100vh; transition:background .2s,color .2s; }
:root[data-theme="dark"] .text-muted { color:var(--muted)!important; }
.brand { font-weight:800; letter-spacing:-.02em; color:var(--ink); text-decoration:none; font-size:1.35rem; }
.site-header { background:var(--header-gradient); border-bottom:1px solid var(--border); }
.home-link { display:inline-flex; align-items:center; gap:.35rem; color:var(--ink); text-decoration:none; font-weight:600; }
.home-link:hover { color:#5279a5; }
.nav-symbol { display:inline-block; min-width:1em; text-align:center; font-size:1.15em; line-height:1; }
.theme-toggle { width:42px; height:42px; display:inline-grid; place-items:center; padding:0; font-size:1.2rem; }
.site-footer { background:var(--footer-gradient); border-top:1px solid var(--border); color:var(--muted); margin-top:auto; }
.site-footer a { color:var(--ink); text-decoration:none; }
.site-footer a:hover { text-decoration:underline; }
.panel { background:var(--surface-gradient); border-radius:14px; border:1px solid var(--border); box-shadow:var(--panel-shadow); }
.btn-ink { background:#17385f; color:#fff; border-color:#17385f; } .btn-ink:hover { background:#24517f; border-color:#24517f; color:#fff; }
.btn-outline-dark { color:var(--ink); border-color:var(--muted); }
.btn-outline-dark:hover { color:var(--paper); background:var(--ink); border-color:var(--ink); }
.text-dark { color:var(--ink)!important; }
.form-control, .form-control-color { color:var(--ink); background-color:var(--surface); border-color:var(--border); }
.form-control::placeholder { color:var(--muted); }
.form-control:focus, .form-control-color:focus { color:var(--ink); background-color:var(--surface); border-color:#5279a5; box-shadow:0 0 0 .2rem rgba(46,91,140,.2); }
.form-text { color:var(--muted); }
/* The profile card */
.pcard { width:100%; max-width:320px; background:var(--surface-gradient); border-radius:20px; overflow:hidden; text-align:center;
         padding-bottom:26px; box-shadow:var(--card-shadow); margin:0 auto; font-family:'Bricolage Grotesque',system-ui,sans-serif; color:var(--ink);
         transition:transform .2s ease,box-shadow .2s ease; }
.pcard:hover { transform:translateY(-5px); box-shadow:var(--card-hover-shadow); }
.pcard-top { height:96px; }
.pcard-avatar { width:92px; height:92px; border-radius:50%; margin:-46px auto 0; border:5px solid var(--surface); color:#fff;
                font-size:2rem; font-weight:800; line-height:82px; position:relative; }
.pcard-photo { position:absolute; inset:0; width:100%; height:100%; border-radius:50%; object-fit:cover; }
.pcard-photo[hidden] { display:none; }
.pcard-name { font-weight:800; font-size:1.5rem; letter-spacing:-.02em; margin:14px 16px 2px; word-break:break-word; }
.pcard-role { color:var(--muted); margin:0 16px 14px; word-break:break-word; }
.pcard-tags { padding:0 18px; display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
.pcard-tags span { background:var(--soft); border-radius:99px; padding:3px 12px; font-size:.82rem; }
.pcard-avatar, .pcard-top { transition:background .15s; }
@media (prefers-reduced-motion:reduce){ body,.pcard,.pcard-avatar,.pcard-top{transition:none} .pcard:hover{transform:none} }
@media (max-width:420px){ .brand{font-size:1.15rem} .home-link{font-size:.92rem} .nav-actions{gap:.4rem!important} }
@media (max-width:400px){ .new-card-label{display:none} .new-card-action{width:42px;height:42px;display:grid;place-items:center;padding:0!important} }
</style>
</head>
<body>
<header class="site-header">
  <nav class="container py-3 d-flex justify-content-between align-items-center" aria-label="Main navigation">
    <div class="d-flex align-items-center gap-3">
      <a class="brand" href="read.php">Cardmaker</a>
      <a class="home-link" href="read.php"><span class="nav-symbol" aria-hidden="true">&#8962;</span>Home</a>
    </div>
    <div class="nav-actions d-flex gap-2 align-items-center">
      <button class="btn btn-outline-dark rounded-circle theme-toggle" id="themeToggle" type="button" aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
        <span class="nav-symbol" id="themeIcon" aria-hidden="true">&#9790;</span>
      </button>
      <a class="btn btn-ink rounded-pill px-3 new-card-action" href="create.php" aria-label="New card" title="New card">
        <span class="nav-symbol" aria-hidden="true">&#43;</span><span class="new-card-label"> New card</span>
      </a>
    </div>
  </nav>
</header>
<main class="container pt-4 pb-5">
<?php if ($m = flash()): ?>
  <div class="alert alert-dark py-2" role="status"><?= e($m) ?></div>
<?php endif;
}

function page_footer(): void { ?>
</main>
<footer class="site-footer">
  <div class="container py-3 d-flex justify-content-between align-items-center">
    <small>&copy; <?= date('Y') ?> Cardmaker</small>
    <a href="read.php">Home</a>
  </div>
</footer>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js"></script>
<script>
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
function updateThemeToggle() {
  const isDark = document.documentElement.dataset.theme === 'dark';
  themeIcon.innerHTML = isDark ? '&#9728;' : '&#9790;';
  themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  themeToggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
  themeToggle.setAttribute('aria-pressed', String(isDark));
}
updateThemeToggle();
themeToggle.addEventListener('click', () => {
  const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = theme;
  try { localStorage.setItem('cardmaker-theme', theme); } catch (err) {}
  updateThemeToggle();
});
if (window.gsap && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  gsap.from('.site-header, main > *, .site-footer', {
    autoAlpha: 0,
    duration: 0.55,
    stagger: 0.08,
    ease: 'power2.out'
  });
  gsap.from('.pcard', {
    autoAlpha: 0,
    duration: 0.6,
    stagger: 0.08,
    delay: 0.1,
    ease: 'power3.out',
    clearProps: 'transform'
  });
}
// Download the card (#card) as a PNG
function downloadCard(btn, filename) {
  const el = document.getElementById('card');
  if (typeof html2canvas === 'undefined') { alert('Download library failed to load. Check your internet connection.'); return; }
  const old = btn.textContent; btn.disabled = true; btn.textContent = 'Preparing…';
  html2canvas(el, { backgroundColor: null, scale: 2 }).then(c => {
    const a = document.createElement('a');
    a.download = (filename || 'profile-card') + '.png';
    a.href = c.toDataURL('image/png');
    document.body.appendChild(a); a.click(); a.remove();
  }).catch(() => alert('Could not create the image.'))
    .finally(() => { btn.disabled = false; btn.textContent = old; });
}
// Share the page link (native share sheet, or copy to clipboard)
async function shareLink(btn, title) {
  const url = btn.dataset.url || location.href;
  try {
    if (navigator.share) { await navigator.share({ title, url }); return; }
    await navigator.clipboard.writeText(url);
  } catch (err) {
    if (err && err.name === 'AbortError') return;
    window.prompt('Copy this link:', url); return;
  }
  const old = btn.textContent; btn.textContent = 'Link copied'; setTimeout(() => btn.textContent = old, 1800);
}
</script>
</body>
</html>
<?php }

// ---- Card markup (used on every page) ----
function render_card(array $p, string $id = 'card'): void {
    $color = e($p['avatar_color']); ?>
<?php $hasPhoto = !empty($p['photo_mime']) && !empty($p['id']); ?>
<div class="pcard" id="<?= e($id) ?>">
  <div class="pcard-top" style="background:<?= $color ?>"></div>
  <div class="pcard-avatar" style="background:<?= $color ?>">
    <span class="pcard-initials" <?= $hasPhoto ? 'hidden' : '' ?>><?= e(initials($p['name'])) ?></span>
    <img class="pcard-photo" src="<?= $hasPhoto ? 'photo.php?id=' . (int)$p['id'] : '' ?>" alt="" <?= $hasPhoto ? '' : 'hidden' ?>>
  </div>
  <h2 class="pcard-name"><?= e($p['name']) ?></h2>
  <p class="pcard-role"><?= e($p['role']) ?></p>
  <div class="pcard-tags"><?php foreach (interest_list($p['interests']) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
</div>
<?php }

// ---- Form with live preview (used by create.php and update.php) ----
function render_form(array $p, array $errors, string $action, string $submitLabel): void {
    $preview = [
        'name'         => $p['name'] !== '' ? $p['name'] : 'Your Name',
        'role'         => $p['role'] !== '' ? $p['role'] : 'Your role',
        'interests'    => $p['interests'] !== '' ? $p['interests'] : 'Add, Some, Interests',
        'avatar_color' => preg_match('/^#[0-9a-f]{6}$/i', $p['avatar_color']) ? $p['avatar_color'] : '#3b6cf6',
'id'           => $p['id'] ?? null,
'photo_mime'   => $p['photo_mime'] ?? null,
    ]; ?>
<div class="row g-4">
  <div class="col-lg-6">
    <form class="panel p-4" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold" for="photo">Profile picture</label>
        <input class="form-control <?= isset($errors['photo']) ? 'is-invalid' : '' ?>" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        <div class="form-text">JPG, PNG, or WebP. Maximum 5 MB. A new image replaces the current one.</div>
            <div class="invalid-feedback"><?= e($errors['photo'] ?? '') ?></div>
          </div>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="name">Name</label>
        <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" maxlength="60" value="<?= e($p['name']) ?>" placeholder="Maria Santos" autocomplete="off">
        <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="role">Role</label>
        <input class="form-control <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" maxlength="60" value="<?= e($p['role']) ?>" placeholder="UI Designer" autocomplete="off">
        <div class="invalid-feedback"><?= e($errors['role'] ?? '') ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold" for="interests">Interests</label>
        <input class="form-control" id="interests" name="interests" maxlength="255" value="<?= e($p['interests']) ?>" placeholder="Hiking, Typography, Coffee" autocomplete="off">
        <div class="form-text">Separate with commas. Up to 8 interests.</div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold" for="avatar_color">Avatar color</label>
        <input class="form-control form-control-color <?= isset($errors['avatar_color']) ? 'is-invalid' : '' ?>" type="color" id="avatar_color" name="avatar_color" value="<?= e($preview['avatar_color']) ?>">
        <div class="invalid-feedback"><?= e($errors['avatar_color'] ?? '') ?></div>
      </div>
      <button class="btn btn-ink rounded-pill px-4" type="submit"><?= e($submitLabel) ?></button>
      <a class="btn btn-link text-dark" href="read.php">Cancel</a>
    </form>
  </div>
  <div class="col-lg-6">
    <div class="panel p-4 text-center" style="position:sticky;top:16px">
      <p class="text-start fw-semibold mb-3">Live preview</p>
      <?php render_card($preview, 'card'); ?>
      <button type="button" class="btn btn-outline-dark rounded-pill mt-4" onclick="downloadCard(this, document.getElementById('name').value.trim().replace(/\s+/g,'-').toLowerCase() || 'profile-card')">Download PNG</button>
    </div>
  </div>
</div>
<script>
(function () {
  const $ = id => document.getElementById(id);
  const card = $('card');
  function update() {
    const name = $('name').value.trim() || 'Your Name';
    const role = $('role').value.trim() || 'Your role';
    const color = $('avatar_color').value;
    const words = name.split(/\s+/).filter(Boolean).slice(0, 2);
    card.querySelector('.pcard-initials').textContent = words.map(w => Array.from(w)[0].toUpperCase()).join('') || '?';
    card.querySelector('.pcard-name').textContent = name;
    card.querySelector('.pcard-role').textContent = role;
    card.querySelector('.pcard-top').style.background = color;
    card.querySelector('.pcard-avatar').style.background = color;
    const seen = new Set(), tags = card.querySelector('.pcard-tags');
    tags.textContent = '';
    ($('interests').value || 'Add, Some, Interests').split(',').map(t => t.trim().slice(0, 24)).filter(Boolean).forEach(t => {
      if (seen.has(t.toLowerCase()) || seen.size >= 8) return;
      seen.add(t.toLowerCase());
      const s = document.createElement('span'); s.textContent = t; tags.appendChild(s);
    });
  }
  const photoInput = $('photo');
  const photoPreview = card.querySelector('.pcard-photo');
  const initials = card.querySelector('.pcard-initials');
  const savedPhoto = photoPreview.getAttribute('src');
  const savedPhotoVisible = !photoPreview.hidden;
  let previewUrl = null;
  photoInput.addEventListener('change', () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    if (photoInput.files && photoInput.files[0]) {
      previewUrl = URL.createObjectURL(photoInput.files[0]);
      photoPreview.src = previewUrl;
      photoPreview.hidden = false;
      initials.hidden = true;
    } else {
      photoPreview.src = savedPhoto || '';
      photoPreview.hidden = !savedPhotoVisible;
      initials.hidden = savedPhotoVisible;
    }
  });
  ['name', 'role', 'interests', 'avatar_color'].forEach(id => { $(id).addEventListener('input', update); });
  update();
})();
</script>
<?php }
