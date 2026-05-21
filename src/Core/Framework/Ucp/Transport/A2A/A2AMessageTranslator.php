<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Translates an A2A Message object into a UCP capability operation. Only
 * **structured** parts (`type: "data"`) are considered — natural-language
 * text-parts require an LLM classifier which is intentionally out of scope.
 *
 * Mapping (action verb → MCP tool name → resource bucket):
 *
 *   ┌────────────────────────┬──────────────────────────┬───────────┐
 *   │ action                 │ tool                     │ resource  │
 *   ├────────────────────────┼──────────────────────────┼───────────┤
 *   │ search_catalog         │ search_catalog           │ catalog   │
 *   │ lookup_products        │ lookup_products          │ catalog   │
 *   │ add_to_cart            │ add_to_cart              │ cart      │
 *   │ remove_from_cart       │ remove_from_cart         │ cart      │
 *   │ get_cart               │ get_cart                 │ cart      │
 *   │ start_checkout         │ create_checkout          │ checkout  │
 *   │ get_checkout           │ get_checkout             │ checkout  │
 *   │ update_checkout        │ update_checkout          │ checkout  │
 *   │ complete_checkout      │ complete_checkout        │ checkout  │
 *   │ cancel_checkout        │ cancel_checkout          │ checkout  │
 *   │ get_order              │ get_order                │ order     │
 *   └────────────────────────┴──────────────────────────┴───────────┘
 *
 * @internal
 */
#[Package('framework')]
class A2AMessageTranslator
{
    /**
     * Direct action → MCP tool mapping. Each entry is `[tool_name, resource_bucket]`.
     *
     * `add_to_cart` / `remove_from_cart` are intentionally absent — they have
     * NO matching MCP tool. We dispatch them via {@see normaliseAddRemove()}
     * which rewrites them to `update_cart` calls with the right arguments.
     */
    private const ACTION_MAP = [
        'search_catalog' => ['search_catalog',    'catalog'],
        'lookup_products' => ['lookup_products',   'catalog'],
        'get_cart' => ['get_cart',          'cart'],
        'create_cart' => ['create_cart',       'cart'],
        'update_cart' => ['update_cart',       'cart'],
        'cancel_cart' => ['cancel_cart',       'cart'],
        'start_checkout' => ['create_checkout',   'checkout'],
        'create_checkout' => ['create_checkout',   'checkout'],
        'get_checkout' => ['get_checkout',      'checkout'],
        'update_checkout' => ['update_checkout',   'checkout'],
        'complete_checkout' => ['complete_checkout', 'checkout'],
        'cancel_checkout' => ['cancel_checkout',   'checkout'],
        'get_order' => ['get_order',         'order'],
    ];

    /**
     * @param array<string, mixed> $message
     */
    public function translate(array $message): ?A2AIntent
    {
        $parts = $message['parts'] ?? [];
        if (!\is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (!\is_array($part) || ($part['type'] ?? null) !== 'data') {
                continue;
            }
            $data = $part['data'] ?? null;
            if (!\is_array($data)) {
                continue;
            }
            $action = $data['action'] ?? null;
            if (!\is_string($action) || $action === '') {
                continue;
            }

            // Special-case the imperative cart actions — these don't map 1:1
            // to MCP tools; rewrite them into an `update_cart` invocation
            // with the right line-items diff.
            if ($action === 'add_to_cart' || $action === 'remove_from_cart') {
                return $this->normaliseAddRemove($action, $data);
            }

            $mapped = self::ACTION_MAP[$action] ?? null;
            if ($mapped === null) {
                continue;
            }

            $arguments = $data;
            unset($arguments['action']);

            return new A2AIntent(
                action: $action,
                toolName: $mapped[0],
                resource: $mapped[1],
                arguments: $arguments,
            );
        }

        return null;
    }

    /**
     * Translate `add_to_cart` / `remove_from_cart` shorthand into a UCP-spec
     * `update_cart` invocation. Both flavours expect:
     *   { "cart_id": "...", "product_id": "...", "quantity": 1 }
     *
     * For `remove_from_cart` the quantity is forced to 0 (Shopware drops
     * line items with quantity 0 from the cart).
     *
     * @param array<string, mixed> $data
     */
    private function normaliseAddRemove(string $action, array $data): A2AIntent
    {
        $cartId = \is_string($data['cart_id'] ?? null) ? $data['cart_id'] : '';
        $productId = \is_string($data['product_id'] ?? null) ? $data['product_id'] : '';
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($action === 'remove_from_cart') {
            $quantity = 0;
        }

        return new A2AIntent(
            action: $action,
            toolName: 'update_cart',
            resource: 'cart',
            arguments: [
                'id' => $cartId,
                'line_items' => [[
                    'item' => ['id' => $productId],
                    'quantity' => $quantity,
                ]],
            ],
        );
    }
}
