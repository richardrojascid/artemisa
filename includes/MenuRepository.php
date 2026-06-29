<?php
declare(strict_types=1);

class MenuRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getFullMenu(bool $includeInactive = false): array
    {
        $catWhere = $includeInactive ? '' : 'WHERE active = 1';
        $itemWhere = $includeInactive ? '' : 'WHERE active = 1';

        $categories = $this->db->query("
            SELECT id, name, sort_order, active
            FROM categories
            {$catWhere}
            ORDER BY sort_order, name
        ")->fetchAll();

        $itemsStmt = $this->db->query("
            SELECT id, category_id, name, description, price, price_double, sort_order, active
            FROM menu_items
            {$itemWhere}
            ORDER BY sort_order, name
        ");
        $items = $itemsStmt->fetchAll();

        $ingsByItem = $this->groupByItemId(
            $this->db->query('SELECT menu_item_id, id, name, removable FROM ingredients')->fetchAll()
        );

        $extrasByItem = $this->groupByItemId(
            $this->db->query('SELECT menu_item_id, id, name, price FROM extras')->fetchAll()
        );

        $itemsByCategory = [];
        foreach ($items as $item) {
            $itemId = $item['id'];
            $item['ingredients'] = $ingsByItem[$itemId] ?? [];
            $item['extras'] = array_map(fn ($e) => $this->formatExtra($e), $extrasByItem[$itemId] ?? []);
            $item['price'] = (float) $item['price'];
            $item['price_double'] = $item['price_double'] !== null ? (float) $item['price_double'] : null;
            $itemsByCategory[$item['category_id']][] = $item;
        }

        foreach ($categories as &$cat) {
            $cat['items'] = $itemsByCategory[$cat['id']] ?? [];
        }

        return $categories;
    }

    public function getItemById(int $id, bool $includeInactive = false): ?array
    {
        $sql = 'SELECT * FROM menu_items WHERE id = ?';
        if (!$includeInactive) {
            $sql .= ' AND active = 1';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            return null;
        }

        $item['price'] = (float) $item['price'];
        $item['price_double'] = $item['price_double'] !== null ? (float) $item['price_double'] : null;

        $stmtIng = $this->db->prepare('SELECT id, name, removable FROM ingredients WHERE menu_item_id = ?');
        $stmtIng->execute([$id]);
        $item['ingredients'] = $stmtIng->fetchAll();

        $stmtExt = $this->db->prepare('SELECT id, name, price FROM extras WHERE menu_item_id = ?');
        $stmtExt->execute([$id]);
        $item['extras'] = array_map(fn ($e) => $this->formatExtra($e), $stmtExt->fetchAll());

        return $item;
    }

    private function groupByItemId(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['menu_item_id']][] = $row;
        }
        return $grouped;
    }

    private function formatExtra(array $extra): array
    {
        $extra['price'] = (float) $extra['price'];
        return $extra;
    }
}
