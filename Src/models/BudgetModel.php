<?php
// src/models/BudgetModel.php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class BudgetModel
{
  public static function get(int $userId, string $monthKey): ?array
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM budgets WHERE user_id=? AND month_key=? LIMIT 1');
    $stmt->execute([$userId, $monthKey]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function upsert(int $userId, string $monthKey, float $amount): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      INSERT INTO budgets (user_id, month_key, amount)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE amount = VALUES(amount), updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([$userId, $monthKey, $amount]);
  }

  public static function listRecent(int $userId, int $months = 12): array
  {
    // Return last N month budgets sorted desc
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT month_key, amount, updated_at
      FROM budgets
      WHERE user_id=?
      ORDER BY month_key DESC
      LIMIT ?
    ');
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $months, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
  }
}
