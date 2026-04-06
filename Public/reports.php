<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/models/ReportModel.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

require_auth();
$user = auth_user();

$title = 'Reports';

$year = (int)($_GET['year'] ?? (int)date('Y'));
if ($year < 2000 || $year > (int)date('Y') + 1) $year = (int)date('Y');

$rangeFrom = str_trim($_GET['from'] ?? date('Y-m-01'));
$rangeTo = str_trim($_GET['to'] ?? date('Y-m-t'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeFrom)) $rangeFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rangeTo)) $rangeTo = date('Y-m-t');

$byCategory = ReportModel::totalsByCategoryForRange((int)$user['id'], $rangeFrom, $rangeTo);
$byMonth = ReportModel::totalsByMonthForYear((int)$user['id'], $year);

// Prepare chart data
$catLabels = array_map(fn($r) => (string)$r['category'], $byCategory);
$catTotals = array_map(fn($r) => (float)$r['total'], $byCategory);

$monthMap = [];
foreach ($byMonth as $r) $monthMap[(string)$r['month_key']] = (float)$r['total'];
$months = [];
$monthTotals = [];
for ($m = 1; $m <= 12; $m++) {
  $mk = sprintf('%04d-%02d', $year, $m);
  $months[] = $mk;
  $monthTotals[] = $monthMap[$mk] ?? 0.0;
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="page-head">
  <div>
    <h1>Reports</h1>
    <p class="muted">See totals by category and month.</p>
  </div>
</div>

<section class="card">
  <div class="card-head">
    <h2>Category totals (range)</h2>
  </div>

  <form method="get" class="form">
    <div class="grid form-grid">
      <div class="field">
        <label>From</label>
        <input type="date" name="from" value="<?= e($rangeFrom) ?>">
      </div>
      <div class="field">
        <label>To</label>
        <input type="date" name="to" value="<?= e($rangeTo) ?>">
      </div>
      <div class="field">
        <label>Year (for monthly chart)</label>
        <input name="year" value="<?= e((string)$year) ?>" placeholder="2026">
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <button class="btn" type="submit">Update</button>
      </div>
    </div>
  </form>

  <div class="grid two-col">
    <div class="card inner">
      <h3>Chart</h3>
      <canvas id="catChart" height="180"></canvas>
      <div class="muted small">Uses Chart.js CDN.</div>
    </div>

    <div class="card inner">
      <h3>Table</h3>
      <?php if (!$byCategory): ?>
        <p class="muted">No data in selected range.</p>
      <?php else: ?>
        <div class="table">
          <div class="row head">
            <div>Category</div>
            <div class="right">Total</div>
          </div>
          <?php foreach ($byCategory as $r): ?>
            <div class="row">
              <div><?= e($r['category']) ?></div>
              <div class="right strong">₹ <?= e(money_fmt((float)$r['total'])) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="card">
  <div class="card-head">
    <h2>Monthly totals (<?= e((string)$year) ?>)</h2>
  </div>

  <div class="grid two-col">
    <div class="card inner">
      <h3>Chart</h3>
      <canvas id="monthChart" height="180"></canvas>
    </div>

    <div class="card inner">
      <h3>Table</h3>
      <div class="table">
        <div class="row head">
          <div>Month</div>
          <div class="right">Total</div>
        </div>
        <?php for ($i = 0; $i < count($months); $i++): ?>
          <div class="row">
            <div><?= e($months[$i]) ?></div>
            <div class="right strong">₹ <?= e(money_fmt((float)$monthTotals[$i])) ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</section>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  window.__REPORTS__ = {
    cat: {
      labels: <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE) ?>,
      totals: <?= json_encode($catTotals) ?>
    },
    month: {
      labels: <?= json_encode($months) ?>,
      totals: <?= json_encode($monthTotals) ?>
    }
  };
</script>

<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
