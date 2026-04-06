<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';
require_once __DIR__ . '/../src/models/BudgetModel.php';
require_once __DIR__ . '/../src/models/ExpenseModel.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

require_auth();
$user = auth_user();

$title = 'Budgets';

$errors = ['month_key' => '', 'amount' => '', 'general' => ''];

$currentMonth = date('Y-m');

if (is_post()) {
  csrf_verify();

  $monthKey = str_trim($_POST['month_key'] ?? '');
  $amountRaw = str_trim($_POST['amount'] ?? '');

  if (!valid_month_key($monthKey)) $errors['month_key'] = 'Month must be YYYY-MM.';
  if (!is_numeric($amountRaw) || (float)$amountRaw < 0) $errors['amount'] = 'Amount must be a number (0 or more).';

  if (!$errors['month_key'] && !$errors['amount']) {
    try {
      BudgetModel::upsert((int)$user['id'], $monthKey, (float)$amountRaw);
      flash_set('success', 'Budget saved.');
      redirect(base_url('/budgets.php'));
    } catch (Throwable $e) {
      $errors['general'] = 'Failed to save budget.';
    }
  }
}

$recentBudgets = BudgetModel::listRecent((int)$user['id'], 12);

// add spending summary for each month in list
$budgetWithSpend = [];
foreach ($recentBudgets as $b) {
  $spent = ExpenseModel::sumForMonth((int)$user['id'], (string)$b['month_key']);
  $budget = (float)$b['amount'];
  $budgetWithSpend[] = [
    'month_key' => $b['month_key'],
    'budget' => $budget,
    'spent' => $spent,
    'remaining' => max(0.0, $budget - $spent),
  ];
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Monthly budgets</h1>
    <p class="muted">Set a budget for each month (YYYY-MM).</p>
  </div>
</div>

<div class="grid two-col">
  <section class="card">
    <div class="card-head">
      <h2>Set / update budget</h2>
    </div>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="grid form-grid">
        <div class="field">
          <label>Month (YYYY-MM)</label>
          <input name="month_key" value="<?= e($_POST['month_key'] ?? $currentMonth) ?>" placeholder="2026-02" required>
          <?php if ($errors['month_key']): ?><div class="hint error"><?= e($errors['month_key']) ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label>Budget amount (₹)</label>
          <input name="amount" value="<?= e($_POST['amount'] ?? '') ?>" placeholder="e.g., 25000" required>
          <?php if ($errors['amount']): ?><div class="hint error"><?= e($errors['amount']) ?></div><?php endif; ?>
        </div>
      </div>

      <button class="btn" type="submit">Save budget</button>
      <div class="hint muted">Tip: Set current month first, then track remaining from Dashboard.</div>
    </form>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Recent budgets</h2>
      <div class="muted small">Last 12 entries</div>
    </div>

    <?php if (!$budgetWithSpend): ?>
      <p class="muted">No budgets yet. Add one on the left.</p>
    <?php else: ?>
      <div class="table">
        <div class="row head">
          <div>Month</div>
          <div class="right">Budget</div>
          <div class="right">Spent</div>
          <div class="right">Remaining</div>
        </div>

        <?php foreach ($budgetWithSpend as $b): ?>
          <div class="row">
            <div><?= e($b['month_key']) ?></div>
            <div class="right strong">₹ <?= e(money_fmt($b['budget'])) ?></div>
            <div class="right">₹ <?= e(money_fmt($b['spent'])) ?></div>
            <div class="right <?= $b['budget'] > 0 && $b['spent'] > $b['budget'] ? 'danger' : '' ?>">
              ₹ <?= e(money_fmt($b['remaining'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
