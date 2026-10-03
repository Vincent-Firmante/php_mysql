<?php
require __DIR__ . '/config.php';

function delete_form(int $id): void { ?>
  <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Delete this card? This cannot be undone.');">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Delete</button>
  </form>
<?php }

// ---- Single card view ----
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $p = null;
    if ($id) {
        $stmt = $db->prepare('SELECT id, name, role, interests, avatar_color, photo_mime FROM profiles WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
    }
    if (!$p) {
        http_response_code(404);
        page_header('Not found');
        echo '<div class="panel p-4"><h1 class="h4">Card not found</h1><p>It may have been deleted.</p><a href="read.php">Back to all cards</a></div>';
        page_footer();
        exit;
    }
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $p['name']), '-')) ?: 'profile-card';
    page_header($p['name']);
    ?>
    <div class="panel p-4 mx-auto text-center" style="max-width:420px">
      <?php render_card($p); ?>
      <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
        <button class="btn btn-ink rounded-pill" type="button" onclick="downloadCard(this, '<?= e($slug) ?>')">Download PNG</button>
        <button class="btn btn-outline-dark rounded-pill" type="button" data-url="" id="shareBtn" onclick="shareLink(this, <?= e(json_encode($p['name'] . ' – ' . $p['role'])) ?>)">Share link</button>
      </div>
      <div class="mt-3">
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="update.php?id=<?= (int)$p['id'] ?>">Edit</a>
        <?php delete_form((int)$p['id']); ?>
      </div>
    </div>
    <p class="text-center mt-3"><a href="read.php" class="text-dark">All cards</a></p>
    <?php
    page_footer();
    exit;
}

// ---- List view ----
$cards = $db->query('SELECT id, name, role, interests, avatar_color, photo_mime, created_at FROM profiles ORDER BY created_at DESC, id DESC')->fetch_all(MYSQLI_ASSOC);
page_header('All cards');
?>
<div class="row g-3 align-items-center mb-4">
  <div class="col-md">
    <h1 class="fw-bold mb-0">Your cards</h1>
  </div>
  <?php if ($cards): ?>
    <div class="col-md-5">
      <label class="visually-hidden" for="cardSearch">Search cards by name, role, or interest</label>
      <input class="form-control" id="cardSearch" type="search" placeholder="Search cards..." autocomplete="off">
    </div>
    <div class="col-md-auto small text-muted" id="searchStatus" aria-live="polite"></div>
  <?php endif; ?>
</div>
<?php if (!$cards): ?>
  <div class="panel p-5 text-center">
    <p class="mb-3">No cards yet. Fill in a short form and your first card appears instantly.</p>
    <a class="btn btn-ink rounded-pill px-4" href="create.php">Make a card</a>
  </div>
<?php else: ?>
  <div class="row g-4">
  <?php foreach ($cards as $c): ?>
    <div class="col-sm-6 col-lg-4" data-card-search="<?= e($c['name'] . ' ' . $c['role'] . ' ' . $c['interests']) ?>">
      <div class="panel p-3 text-center h-100">
        <?php render_card($c, 'card-' . (int)$c['id']); ?>
        <div class="mt-3">
          <a class="btn btn-sm btn-ink rounded-pill" href="read.php?id=<?= (int)$c['id'] ?>">Open</a>
          <a class="btn btn-sm btn-outline-secondary rounded-pill" href="update.php?id=<?= (int)$c['id'] ?>">Edit</a>
          <?php delete_form((int)$c['id']); ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <p class="text-center text-muted mt-4" id="noSearchResults" hidden>No cards match your search.</p>
  <script>
  (() => {
    const input = document.getElementById('cardSearch');
    const cards = Array.from(document.querySelectorAll('[data-card-search]'));
    const status = document.getElementById('searchStatus');
    const emptyState = document.getElementById('noSearchResults');

    function filterCards() {
      const query = input.value.trim().toLocaleLowerCase();
      let visibleCount = 0;
      cards.forEach(card => {
        const matches = card.dataset.cardSearch.toLocaleLowerCase().includes(query);
        card.hidden = !matches;
        if (matches) visibleCount++;
      });
      emptyState.hidden = visibleCount > 0;
      status.textContent = `${visibleCount} of ${cards.length} cards`;
    }

    input.addEventListener('input', filterCards);
    filterCards();
  })();
  </script>
<?php endif;
page_footer();
