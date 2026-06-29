<?php
declare(strict_types=1);

class AdminRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function saveCategory(array $data): int
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare('UPDATE categories SET name = ?, sort_order = ?, active = ? WHERE id = ?');
            $stmt->execute([
                $data['name'],
                (int) ($data['sort_order'] ?? 0),
                !empty($data['active']) ? 1 : 0,
                (int) $data['id'],
            ]);
            return (int) $data['id'];
        }

        $stmt = $this->db->prepare('INSERT INTO categories (name, sort_order, active) VALUES (?, ?, ?)');
        $stmt->execute([
            $data['name'],
            (int) ($data['sort_order'] ?? 0),
            !empty($data['active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteCategory(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function saveItem(array $data): int
    {
        $priceDouble = isset($data['price_double']) && $data['price_double'] !== ''
            ? (float) $data['price_double']
            : null;

        if (!empty($data['id'])) {
            $stmt = $this->db->prepare('
                UPDATE menu_items
                SET category_id = ?, name = ?, description = ?, price = ?, price_double = ?, sort_order = ?, active = ?
                WHERE id = ?
            ');
            $stmt->execute([
                (int) $data['category_id'],
                $data['name'],
                $data['description'] ?? null,
                (float) $data['price'],
                $priceDouble,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['active']) ? 1 : 0,
                (int) $data['id'],
            ]);
            $itemId = (int) $data['id'];
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO menu_items (category_id, name, description, price, price_double, sort_order, active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                (int) $data['category_id'],
                $data['name'],
                $data['description'] ?? null,
                (float) $data['price'],
                $priceDouble,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['active']) ? 1 : 0,
            ]);
            $itemId = (int) $this->db->lastInsertId();
        }

        if (isset($data['ingredients']) && is_array($data['ingredients'])) {
            $this->replaceIngredients($itemId, $data['ingredients']);
        }
        if (isset($data['extras']) && is_array($data['extras'])) {
            $this->replaceExtras($itemId, $data['extras']);
        }

        return $itemId;
    }

    public function deleteItem(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function replaceIngredients(int $itemId, array $ingredients): void
    {
        $del = $this->db->prepare('DELETE FROM ingredients WHERE menu_item_id = ?');
        $del->execute([$itemId]);

        $stmt = $this->db->prepare('INSERT INTO ingredients (menu_item_id, name, removable) VALUES (?, ?, ?)');
        foreach ($ingredients as $ing) {
            if (empty($ing['name'])) {
                continue;
            }
            $stmt->execute([$itemId, trim($ing['name']), !empty($ing['removable']) ? 1 : 0]);
        }
    }

    private function replaceExtras(int $itemId, array $extras): void
    {
        $del = $this->db->prepare('DELETE FROM extras WHERE menu_item_id = ?');
        $del->execute([$itemId]);

        $stmt = $this->db->prepare('INSERT INTO extras (menu_item_id, name, price) VALUES (?, ?, ?)');
        foreach ($extras as $extra) {
            if (empty($extra['name'])) {
                continue;
            }
            $stmt->execute([$itemId, trim($extra['name']), (float) ($extra['price'] ?? 0)]);
        }
    }
}
