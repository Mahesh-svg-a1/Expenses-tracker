<?php
// src/models/ExpenseModel.php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class ExpenseModel
{
  public static function create(
    int $userId,
    float $amount,
    string $date,
    ?int $categoryId,
    ?string $note
  ): void {
    $pdo = db();
    $stmt = $pdo->prepare('
      INSERT INTO expenses (user_id, category_id, amount, expense_date, note)
      VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $categoryId, $amount, $date, $note]);
  }

  public static function update(
    int $userId,
    int $expenseId,
    float $amount,
    string $date,
    ?int $categoryId,
    ?string $note
  ): void {
    $pdo = db();
    $stmt = $pdo->prepare('
      UPDATE expenses
      SET category_id = ?, amount = ?, expense_date = ?, note = ?
      WHERE id = ? AND user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$categoryId, $amount, $date, $note, $expenseId, $userId]);
  }

  public static function delete(int $userId, int $expenseId): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$expenseId, $userId]);
  }

  public static function find(int $userId, int $expenseId): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.*, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.id = ? AND e.user_id = ?
      LIMIT 1
    ');
    $stmt->execute([$expenseId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  // ✅ UPDATED LIST METHOD
  public static function list(int $userId, array $filters, int $limit = 250): array
  {
    $pdo = db();

    $where = ['e.user_id = :uid'];
    $params = [':uid' => $userId];

    if (!empty($filters['q'])) {
      // IMPORTANT: Do NOT reuse same named parameter twice with emulation OFF
      $where[] = '(e.note LIKE :q1 OR c.name LIKE :q2)';
      $params[':q1'] = '%' . $filters['q'] . '%';
      $params[':q2'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['category_id'])) {
      $where[] = 'e.category_id = :cid';
      $params[':cid'] = (int)$filters['category_id'];
    }

    if (!empty($filters['from'])) {
      $where[] = 'e.expense_date >= :fromDate';
      $params[':fromDate'] = $filters['from'];
    }

    if (!empty($filters['to'])) {
      $where[] = 'e.expense_date <= :toDate';
      $params[':toDate'] = $filters['to'];
    }

    $sql = '
      SELECT e.id, e.amount, e.expense_date, e.note, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY e.expense_date DESC, e.id DESC
      LIMIT ' . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public static function sumForDate(int $userId, string $date): float
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE user_id=? AND expense_date=?');
    $stmt->execute([$userId, $date]);
    return (float)$stmt->fetchColumn();
  }

  public static function sumForMonth(int $userId, string $monthKey): float
  {
    // monthKey: YYYY-MM
    $start = $monthKey . '-01';
    $end = date('Y-m-t', strtotime($start));

    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(SUM(amount),0) AS total
      FROM expenses
      WHERE user_id = ? AND expense_date BETWEEN ? AND ?
    ');
    $stmt->execute([$userId, $start, $end]);
    return (float)$stmt->fetchColumn();
  }

  public static function recent(int $userId, int $limit = 7): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT e.id, e.amount, e.expense_date, e.note, c.name AS category_name
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ?
      ORDER BY e.expense_date DESC, e.id DESC
      LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }
}
