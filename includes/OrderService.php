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
                INSERT INTO orders (table_number, waiter_name, subtotal, tip_amount, total, include_tip, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            $stmt->execute([
                $payload['table_number'] ?? null,
                $payload['waiter_name'] ?? null,
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
        return $order;
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
