<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/models/ExpenseModel.php';
require_once __DIR__ . '/../src/models/CategoryModel.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

require_auth();
$user = auth_user();

$title = 'Expenses';

$categories = CategoryModel::allForUser((int)$user['id']);

$addErrors = ['amount' => '', 'expense_date' => '', 'general' => ''];

// Handle add expense
if (is_post() && ($_POST['action'] ?? '') === 'add') {
  csrf_verify();

  $amountRaw = str_trim($_POST['amount'] ?? '');
  $date = str_trim($_POST['expense_date'] ?? '');
  $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
  $note = str_trim($_POST['note'] ?? '');
  $note = $note === '' ? null : mb_substr($note, 0, 255);

  if (!is_numeric($amountRaw) || (float)$amountRaw <= 0) $addErrors['amount'] = 'Amount must be a positive number.';
  if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $addErrors['expense_date'] = 'Valid date is required (YYYY-MM-DD).';

  // Validate category ownership if provided
  if ($categoryId !== null) {
    $ownedIds = array_map(fn($c) => (int)$c['id'], $categories);
    if (!in_array($categoryId, $ownedIds, true)) $categoryId = null;
  }

  if (!$addErrors['amount'] && !$addErrors['expense_date']) {
    try {
      ExpenseModel::create((int)$user['id'], (float)$amountRaw, $date, $categoryId, $note);
      flash_set('success', 'Expense added.');
      redirect(base_url('/expenses.php'));
    } catch (Throwable $e) {
      $addErrors['general'] = 'Failed to add expense.';
    }
  }
}

// Handle delete expense
if (is_post() && ($_POST['action'] ?? '') === 'delete') {
  csrf_verify();
  $expenseId = (int)($_POST['expense_id'] ?? 0);
  if ($expenseId > 0) {
    ExpenseModel::delete((int)$user['id'], $expenseId);
    flash_set('success', 'Expense deleted.');
  }
  redirect(base_url('/expenses.php'));
}

// Filters (GET)
$filters = [
  'q' => str_trim($_GET['q'] ?? ''),
  'category_id' => str_trim($_GET['category_id'] ?? ''),
  'from' => str_trim($_GET['from'] ?? ''),
  'to' => str_trim($_GET['to'] ?? ''),
];

if ($filters['from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['from'])) $filters['from'] = '';
if ($filters['to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['to'])) $filters['to'] = '';
if ($filters['category_id'] !== '' && !ctype_digit($filters['category_id'])) $filters['category_id'] = '';

$rows = ExpenseModel::list((int)$user['id'], $filters, 250);

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Expenses</h1>
    <p class="muted">Add, filter, edit, and manage your daily spending.</p>
  </div>
</div>

<div class="grid two-col">
  <section id="add" class="card">
    <div class="card-head">
      <h2>Add expense</h2>
    </div>

    <?php if ($addErrors['general']): ?>
      <div class="alert"><?= e($addErrors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add" />

      <div class="grid form-grid">
        <div class="field">
          <label>Amount (₹)</label>
          <input name="amount" inputmode="decimal" placeholder="e.g., 120.50" value="<?= e($_POST['amount'] ?? '') ?>" required>
          <?php if ($addErrors['amount']): ?><div class="hint error"><?= e($addErrors['amount']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Date</label>
          <input type="date" name="expense_date" value="<?= e($_POST['expense_date'] ?? date('Y-m-d')) ?>" required>
          <?php if ($addErrors['expense_date']): ?><div class="hint error"><?= e($addErrors['expense_date']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Category</label>
          <select name="category_id">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)($c['id']) === (string)($_POST['category_id'] ?? '') ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint muted">Manage in <a href="<?= e(base_url('/categories.php')) ?>">Categories</a></div>
        </div>

        <div class="field">
          <label>Note</label>
          <input name="note" maxlength="255" placeholder="e.g., lunch / taxi / groceries" value="<?= e($_POST['note'] ?? '') ?>">
          <div class="hint muted">Optional. Max 255 chars.</div>
        </div>
      </div>

      <button class="btn" type="submit">Add expense</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Search & filters</h2>
    </div>

    <form method="get" class="form">
      <div class="grid form-grid">
        <div class="field">
          <label>Search</label>
          <input name="q" value="<?= e($filters['q']) ?>" placeholder="Search note or category">
        </div>

        <div class="field">
          <label>Category</label>
          <select name="category_id">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (string)$filters['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label>From</label>
          <input type="date" name="from" value="<?= e($filters['from']) ?>">
        </div>

        <div class="field">
          <label>To</label>
          <input type="date" name="to" value="<?= e($filters['to']) ?>">
        </div>
      </div>

      <div class="row-actions">
        <button class="btn" type="submit">Apply</button>
        <a class="btn btn-ghost" href="<?= e(base_url('/expenses.php')) ?>">Reset</a>
      </div>
    </form>
  </section>
</div>

<section class="card">
  <div class="card-head">
    <h2>Expense list</h2>
    <div class="muted small"><?= count($rows) ?> result(s)</div>
  </div>

  <?php if (!$rows): ?>
    <p class="muted">No expenses found. Try adding one or adjusting filters.</p>
  <?php else: ?>
    <div class="table">
      <div class="row head">
        <div>Date</div>
        <div>Category</div>
        <div>Note</div>
        <div class="right">Amount</div>
        <div class="right">Actions</div>
      </div>

      <?php foreach ($rows as $r): ?>
        <div class="row">
          <div><?= e($r['expense_date']) ?></div>
          <div><?= e($r['category_name'] ?? 'Uncategorized') ?></div>
          <div class="truncate"><?= e($r['note'] ?? '') ?></div>
          <div class="right strong">₹ <?= e(money_fmt((float)$r['amount'])) ?></div>
          <div class="right">
            <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/expense_edit.php?id=' . (int)$r['id'])) ?>">Edit</a>

            <form method="post" class="inline" data-confirm="Delete this expense?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="expense_id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
