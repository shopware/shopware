<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Output of {@see A2AMessageTranslator} — pairs an A2A action verb with the
 * UCP MCP tool that implements it and supplies a human-readable summary for
 * the response text part.
 *
 * @internal
 */
#[Package('framework')]
final class A2AIntent
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public readonly string $action,
        public readonly string $toolName,
        public readonly string $resource,
        public readonly array $arguments,
    ) {
    }

    /**
     * @param array<string, mixed> $toolResult
     */
    public function summary(array $toolResult): string
    {
        return match ($this->resource) {
            'catalog' => $this->summariseCatalog($toolResult),
            'cart' => $this->summariseCart($toolResult),
            'checkout' => $this->summariseCheckout($toolResult),
            'order' => $this->summariseOrder($toolResult),
            default => 'OK',
        };
    }

    /**
     * @param array<string, mixed> $r
     */
    private function summariseCatalog(array $r): string
    {
        $items = $r['items'] ?? $r['products'] ?? [];
        if (!\is_array($items)) {
            return 'Catalog query completed.';
        }
        if ($items === []) {
            return 'No matching products found.';
        }

        return 'Found ' . \count($items) . ' product(s).';
    }

    /**
     * @param array<string, mixed> $r
     */
    private function summariseCart(array $r): string
    {
        $count = is_countable($r['line_items'] ?? null) ? \count($r['line_items']) : 0;
        $total = $r['totals'] ?? [];
        $sum = 0;
        $currency = '';
        if (\is_array($total)) {
            foreach ($total as $entry) {
                if (\is_array($entry) && ($entry['type'] ?? null) === 'total') {
                    $sum = $entry['amount'] ?? 0;
                    $currency = $entry['currency'] ?? '';
                    break;
                }
            }
        }

        return \sprintf('Cart now has %d item(s), total %s %.2f.', $count, $currency, $sum / 100);
    }

    /**
     * @param array<string, mixed> $r
     */
    private function summariseCheckout(array $r): string
    {
        $status = $r['status'] ?? 'unknown';
        if ($status === 'completed' && isset($r['order_id'])) {
            return \sprintf('Order placed: %s.', $r['order_id']);
        }
        $continue = $r['continue_url'] ?? null;
        if (\is_string($continue) && $continue !== '') {
            return \sprintf('Checkout status: %s. Continue at %s.', $status, $continue);
        }

        return \sprintf('Checkout status: %s.', $status);
    }

    /**
     * @param array<string, mixed> $r
     */
    private function summariseOrder(array $r): string
    {
        $orderNumber = $r['order_number'] ?? $r['number'] ?? '?';

        return \sprintf('Order #%s details retrieved.', $orderNumber);
    }
}
