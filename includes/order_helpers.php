<?php
function fetchOrderItemsByOrderIds(PDO $pdo, array $orderIds): array
{
    $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
    if(empty($orderIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT oi.order_id, oi.product_id, oi.quantity, oi.price_per_item, oi.total_price,
                p.name AS product_name, p.image AS product_image, p.category AS product_category
         FROM order_items oi
         JOIN product p ON oi.product_id = p.id
         WHERE oi.order_id IN ($placeholders)
         ORDER BY oi.order_id ASC, oi.id ASC"
    );
    $stmt->execute($orderIds);

    $itemsByOrder = [];
    foreach($stmt->fetchAll() as $item) {
        $orderId = (int) $item['order_id'];
        if(!isset($itemsByOrder[$orderId])) {
            $itemsByOrder[$orderId] = [];
        }
        $itemsByOrder[$orderId][] = $item;
    }

    return $itemsByOrder;
}

function enrichOrdersWithItems(array $orders, array $itemsByOrder): array
{
    foreach($orders as &$order) {
        $orderId = (int) $order['id'];
        $items = $itemsByOrder[$orderId] ?? [];

        if(empty($items)) {
            $legacyQuantity = (int) ($order['quantity'] ?? 0);
            $legacyPrice = isset($order['product_price'])
                ? (float) $order['product_price']
                : (float) (($order['total_amount'] ?? 0) - ($order['delivery_fee'] ?? 0));

            $items = [[
                'order_id' => $orderId,
                'product_id' => $order['product_id'] ?? 0,
                'quantity' => $legacyQuantity,
                'price_per_item' => $legacyPrice,
                'total_price' => round($legacyPrice * $legacyQuantity, 2),
                'product_name' => $order['product_name'] ?? 'Product',
                'product_image' => $order['product_image'] ?? null,
                'product_category' => $order['product_category'] ?? ''
            ]];
        }

        $order['items'] = $items;
        $order['item_count'] = count($items);
        $order['total_quantity'] = array_sum(array_map(static fn($item) => (int) $item['quantity'], $items));
        $order['subtotal_amount'] = round(
            array_sum(array_map(static fn($item) => (float) $item['total_price'], $items)),
            2
        );

        $primaryItem = $items[0];
        $order['primary_product_name'] = $primaryItem['product_name'];
        $order['primary_product_image'] = $primaryItem['product_image'];

        if(count($items) > 1) {
            $order['items_summary'] = $primaryItem['product_name'] . ' +' . (count($items) - 1) . ' more';
        } else {
            $order['items_summary'] = $primaryItem['product_name'];
        }

        $summaryParts = [];
        foreach($items as $item) {
            $summaryParts[] = $item['product_name'] . ' x' . (int) $item['quantity'];
        }
        $order['items_summary_full'] = implode(', ', $summaryParts);
    }
    unset($order);

    return $orders;
}
?>
