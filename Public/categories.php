<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/models/CategoryModel.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

require_auth();
$user = auth_user();

$title = 'Categories';
$errors = ['name' => '', 'general' => ''];

if (is_post() && ($_POST['action'] ?? '') === 'add') {
  csrf_verify();

  $name = str_trim($_POST['name'] ?? '');
  $name = mb_substr($name, 0, 60);

  if ($name === '' || mb_strlen($name) < 2) {
    $errors['name'] = 'Category name must be at least 2 characters.';
  } else {
    try {
      CategoryModel::create((int)$user['id'], $name);
      flash_set('success', 'Category added.');
      redirect(base_url('/categories.php'));
    } catch (Throwable $e) {
      $errors['general'] = 'Could not add category (maybe duplicate?).';
    }
  }
}

if (is_post() && ($_POST['action'] ?? '') === 'delete') {
  csrf_verify();
  $cid = (int)($_POST['category_id'] ?? 0);
  if ($cid > 0) {
    CategoryModel::delete((int)$user['id'], $cid);
    flash_set('success', 'Category deleted.');
  }
  redirect(base_url('/categories.php'));
}

$categories = CategoryModel::allForUser((int)$user['id']);

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Categories</h1>
    <p class="muted">Keep your spending organized.</p>
  </div>
</div>

<div class="grid two-col">
  <section class="card">
    <div class="card-head">
      <h2>Add category</h2>
    </div>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">

      <div class="field">
        <label>Name</label>
        <input name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g., Food, Transport, Rent" required>
        <?php if ($errors['name']): ?><div class="hint error"><?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <button class="btn" type="submit">Add</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Your categories</h2>
      <div class="muted small"><?= count($categories) ?> total</div>
    </div>

    <?php if (!$categories): ?>
      <p class="muted">No categories yet. Add some to make tracking easier.</p>
    <?php else: ?>
      <div class="pill-grid">
        <?php foreach ($categories as $c): ?>
          <div class="pill">
            <span><?= e($c['name']) ?></span>
            <form method="post" class="inline" data-confirm="Delete category '<?= e($c['name']) ?>'? (Expenses become Uncategorized)">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
              <button class="pill-x" type="submit" aria-label="Delete">×</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
