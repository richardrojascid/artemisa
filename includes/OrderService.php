<?php
declare(strict_types=1);

class OrderService
{
    private PDO $db;
    private MenuRepository $menu;
    private Settings $settings;

    public function __construct(PDO $db, MenuRepository $menu, ?Settings $settings = null)
    {
        $this->db = $db;
        $this->menu = $menu;
        $this->settings = $settings ?? new Settings($db);
    }

    public function createOrder(array $payload): array
    {
        $items = $payload['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException('El pedido debe incluir al menos un producto.');
        }

        $processedItems = [];
        $subtotal = 0.0;

        foreach ($items as $cartItem) {
            $processed = $this->processCartItem($cartItem);
            $processedItems[] = $processed;
            $subtotal += $processed['line_total'];
        }

        $tipMode = $payload['tip_mode'] ?? 'percent';
        $tipPercent = (float) ($payload['tip_percent'] ?? $this->settings->getTipPercent());

        if ($tipMode === 'manual') {
            $tipAmount = max(0, round((float) ($payload['tip_amount'] ?? 0)));
        } elseif ($tipMode === 'percent') {
            $tipAmount = round($subtotal * ($tipPercent / 100));
        } else {
            $tipAmount = 0;
        }

        $includeTip = $tipAmount > 0;
        $total = $subtotal + $tipAmount;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO orders (table_number, waiter_name, client_name, order_type, subtotal, tip_amount, total, include_tip, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $orderType = ($payload['order_type'] ?? 'servir') === 'llevar' ? 'llevar' : 'servir';
            $clientName = trim((string) ($payload['client_name'] ?? ''));
            $stmt->execute([
                $payload['table_number'] ?? null,
                $payload['waiter_name'] ?? null,
                $clientName !== '' ? $clientName : null,
                $orderType,
                $subtotal,
                $tipAmount,
                $total,
                $includeTip ? 1 : 0,
                date('Y-m-d H:i:s'),
            ]);
            $orderId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare("
                INSERT INTO order_items
                (order_id, menu_item_id, item_name, unit_price, quantity, extras_total, line_total, removed_ingredients, added_extras, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($processedItems as $pi) {
                $itemStmt->execute([
                    $orderId,
                    $pi['menu_item_id'],
                    $pi['item_name'],
                    $pi['unit_price'],
                    $pi['quantity'],
                    $pi['extras_total'],
                    $pi['line_total'],
                    json_encode($pi['removed_ingredients'], JSON_UNESCAPED_UNICODE),
                    json_encode($pi['added_extras'], JSON_UNESCAPED_UNICODE),
                    $pi['notes'] ?? null,
                ]);
            }

            $this->db->commit();

            return [
                'id' => $orderId,
                'table_number' => $payload['table_number'] ?? null,
                'waiter_name' => $payload['waiter_name'] ?? null,
                'client_name' => $clientName !== '' ? $clientName : null,
                'order_type' => $orderType,
                'subtotal' => $subtotal,
                'tip_percent' => $tipMode === 'percent' ? $tipPercent : null,
                'tip_amount' => $tipAmount,
                'tip_mode' => $tipMode,
                'include_tip' => $includeTip,
                'total' => $total,
                'items' => $processedItems,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getOrder(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $itemStmt = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemStmt->execute([$id]);
        $items = $itemStmt->fetchAll();

        foreach ($items as &$item) {
            $item['removed_ingredients'] = json_decode($item['removed_ingredients'] ?? '[]', true) ?: [];
            $item['added_extras'] = json_decode($item['added_extras'] ?? '[]', true) ?: [];
            $item['unit_price'] = (float) $item['unit_price'];
            $item['line_total'] = (float) $item['line_total'];
            $item['quantity'] = (int) $item['quantity'];
        }

        $order['items'] = $items;
        $order['subtotal'] = (float) $order['subtotal'];
        $order['tip_amount'] = (float) ($order['tip_amount'] ?? 0);
        $order['total'] = (float) $order['total'];
        $order['include_tip'] = !empty($order['include_tip']);
        $order['order_type'] = $order['order_type'] ?? (
            strtoupper((string) ($order['table_number'] ?? '')) === 'PL' ? 'llevar' : 'servir'
        );
        return $order;
    }

    /**
     * @return array{orders: array<int, array>, pagination: array{page: int, per_page: int, total: int, pages: int}}
     */
    public function listOrders(string $query = '', int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $query = trim($query);

        $where = '';
        $params = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $where = 'WHERE (
                CAST(id AS TEXT) LIKE ?
                OR IFNULL(client_name, \'\') LIKE ?
                OR IFNULL(waiter_name, \'\') LIKE ?
                OR IFNULL(table_number, \'\') LIKE ?
                OR IFNULL(order_type, \'\') LIKE ?
                OR CAST(total AS TEXT) LIKE ?
                OR CAST(subtotal AS TEXT) LIKE ?
                OR IFNULL(created_at, \'\') LIKE ?
            )';
            $params = [$like, $like, $like, $like, $like, $like, $like, $like];
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM orders {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $listParams = array_merge($params, [$perPage, $offset]);
        $stmt = $this->db->prepare("
            SELECT id, table_number, waiter_name, client_name, order_type,
                   subtotal, tip_amount, total, created_at
            FROM orders
            {$where}
            ORDER BY datetime(created_at) DESC, id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($listParams);
        $rows = $stmt->fetchAll();

        $orders = array_map(fn (array $row) => $this->formatOrderSummary($row), $rows);

        return [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function formatOrderSummary(array $row): array
    {
        $orderType = $row['order_type'] ?? 'servir';
        if ($orderType !== 'llevar' && strtoupper((string) ($row['table_number'] ?? '')) === 'PL') {
            $orderType = 'llevar';
        }

        return [
            'id' => (int) $row['id'],
            'table_number' => $row['table_number'],
            'waiter_name' => $row['waiter_name'],
            'client_name' => $row['client_name'],
            'order_type' => $orderType,
            'subtotal' => (float) $row['subtotal'],
            'tip_amount' => (float) ($row['tip_amount'] ?? 0),
            'total' => (float) $row['total'],
            'created_at' => $row['created_at'],
            'service_label' => $orderType === 'llevar' ? 'Para llevar' : 'Para servir',
            'staff_label' => $orderType === 'llevar' ? 'Cajera(o)' : 'Mesera(o)',
        ];
    }

    private function processCartItem(array $cartItem): array
    {
        $menuItemId = (int) ($cartItem['menu_item_id'] ?? 0);
        $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
        $notes = trim($cartItem['notes'] ?? '');

        $menuItem = $this->menu->getItemById($menuItemId);
        if (!$menuItem) {
            throw new InvalidArgumentException("Producto no encontrado (ID: {$menuItemId}).");
        }

        $unitPrice = (float) $menuItem['price'];
        $size = $cartItem['size'] ?? 'simple';
        if ($size === 'doble' && $menuItem['price_double'] !== null) {
            $unitPrice = (float) $menuItem['price_double'];
        }

        $itemName = $menuItem['name'];
        if ($menuItem['price_double'] !== null) {
            $itemName .= $size === 'doble' ? ' (Doble)' : ' (Simple)';
        }

        $removed = $cartItem['removed_ingredients'] ?? [];
        $addedExtras = $cartItem['added_extras'] ?? [];

        $extrasTotal = 0.0;
        $validatedExtras = [];

        $extrasMap = [];
        foreach ($menuItem['extras'] as $extra) {
            $extrasMap[$extra['id']] = $extra;
            $extrasMap[$extra['name']] = $extra;
        }

        foreach ($addedExtras as $extra) {
            if (is_array($extra)) {
                $extraId = $extra['id'] ?? null;
                $extraName = $extra['name'] ?? '';
                if ($extraId && isset($extrasMap[$extraId])) {
                    $validated = $extrasMap[$extraId];
                } elseif ($extraName && isset($extrasMap[$extraName])) {
                    $validated = $extrasMap[$extraName];
                } else {
                    continue;
                }
            } elseif (is_numeric($extra) && isset($extrasMap[(int) $extra])) {
                $validated = $extrasMap[(int) $extra];
            } else {
                continue;
            }

            $validatedExtras[] = ['name' => $validated['name'], 'price' => (float) $validated['price']];
            $extrasTotal += (float) $validated['price'];
        }

        $validIngredientNames = array_column($menuItem['ingredients'], 'name');
        $validatedRemoved = array_values(array_intersect($removed, $validIngredientNames));

        $lineTotal = ($unitPrice + $extrasTotal) * $quantity;

        return [
            'menu_item_id' => $menuItemId,
            'item_name' => $itemName,
            'unit_price' => $unitPrice,
            'size' => $size,
            'quantity' => $quantity,
            'extras_total' => $extrasTotal,
            'line_total' => $lineTotal,
            'removed_ingredients' => $validatedRemoved,
            'added_extras' => $validatedExtras,
            'notes' => $notes,
        ];
    }
}
