<?php
// src/models/CategoryModel.php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class CategoryModel
{
  public static function allForUser(int $userId): array
  {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
  }

  public static function create(int $userId, string $name): void
  {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO categories (user_id, name) VALUES (?, ?)');
    $stmt->execute([$userId, $name]);
  }

  public static function delete(int $userId, int $categoryId): void
  {
    $pdo = db();
    // Ensure ownership
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$categoryId, $userId]);
  }
}
