<?php
// src/models/ReportModel.php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class ReportModel
{
  public static function totalsByCategoryForRange(int $userId, string $from, string $to): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT COALESCE(c.name, "Uncategorized") AS category, COALESCE(SUM(e.amount),0) AS total
      FROM expenses e
      LEFT JOIN categories c ON c.id = e.category_id
      WHERE e.user_id = ? AND e.expense_date BETWEEN ? AND ?
      GROUP BY category
      ORDER BY total DESC
    ');
    $stmt->execute([$userId, $from, $to]);
    return $stmt->fetchAll();
  }

  public static function totalsByMonthForYear(int $userId, int $year): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('
      SELECT DATE_FORMAT(expense_date, "%Y-%m") AS month_key, COALESCE(SUM(amount),0) AS total
      FROM expenses
      WHERE user_id = ? AND YEAR(expense_date) = ?
      GROUP BY month_key
      ORDER BY month_key ASC
    ');
    $stmt->execute([$userId, $year]);
    return $stmt->fetchAll();
  }
}
