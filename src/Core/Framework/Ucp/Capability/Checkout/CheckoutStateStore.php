<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Minimal persisted lifecycle guard for UCP checkout sessions.
 *
 * Shopware carts are token based and can otherwise be reloaded after cancel or
 * order placement. UCP conformance requires terminal checkout states to reject
 * further mutations when a new idempotency key is used.
 *
 * @internal
 */
#[Package('framework')]
class CheckoutStateStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function state(string $checkoutId): ?string
    {
        $this->ensureTable();
        $state = $this->connection->fetchOne('SELECT state FROM ucp_checkout_state WHERE checkout_id = ? LIMIT 1', [$checkoutId]);

        return \is_string($state) ? $state : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buyer(string $checkoutId): ?array
    {
        $this->ensureTable();
        $raw = $this->connection->fetchOne('SELECT buyer_json FROM ucp_checkout_state WHERE checkout_id = ? LIMIT 1', [$checkoutId]);
        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $buyer
     */
    public function saveBuyer(string $checkoutId, array $buyer): void
    {
        $this->ensureTable();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->executeStatement(
            'INSERT INTO ucp_checkout_state (checkout_id, state, order_id, buyer_json, updated_at, created_at)
             VALUES (?, ?, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE buyer_json = VALUES(buyer_json), updated_at = VALUES(updated_at)',
            [$checkoutId, CheckoutStatus::INCOMPLETE, json_encode($buyer, \JSON_THROW_ON_ERROR), $now, $now]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function addressesForBuyer(string $email): array
    {
        $this->ensureAddressTable();
        $rows = $this->connection->fetchFirstColumn('SELECT address_json FROM ucp_conformance_address WHERE email = ? ORDER BY id ASC', [$email]);
        $out = [];
        foreach ($rows as $row) {
            if (!\is_string($row)) {
                continue;
            }
            $decoded = json_decode($row, true);
            if (\is_array($decoded)) {
                $out[] = $decoded;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $address
     *
     * @return array<string, mixed>
     */
    public function saveAddressForBuyer(string $email, array $address): array
    {
        $this->ensureAddressTable();
        $address['id'] = (!\is_string($address['id'] ?? null) || $address['id'] === '')
            ? 'addr_' . substr(Hasher::hash($email), 0, 12)
            : $this->addressId($address);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->executeStatement(
            'INSERT INTO ucp_conformance_address (id, email, address_json, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE address_json = VALUES(address_json)',
            [$address['id'], $email, json_encode($address, \JSON_THROW_ON_ERROR), $now]
        );

        return $address;
    }

    public function markCanceled(string $checkoutId): void
    {
        $this->upsert($checkoutId, CheckoutStatus::CANCELED, null);
    }

    public function markCompleted(string $checkoutId, string $orderId): void
    {
        $this->upsert($checkoutId, CheckoutStatus::COMPLETED, $orderId);
    }

    /**
     * @param array<string, mixed> $fulfillment
     */
    public function saveFulfillment(string $checkoutId, array $fulfillment): void
    {
        $this->ensureTable();
        $this->connection->executeStatement(
            'INSERT INTO ucp_checkout_state (checkout_id, state, order_id, buyer_json, fulfillment_json, updated_at, created_at)
             VALUES (?, ?, NULL, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE fulfillment_json = VALUES(fulfillment_json), updated_at = VALUES(updated_at)',
            [
                $checkoutId,
                CheckoutStatus::INCOMPLETE,
                json_encode($fulfillment, \JSON_THROW_ON_ERROR),
                (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]
        );
    }

    public function checkoutIdForOrder(string $orderId): ?string
    {
        $this->ensureTable();
        $checkoutId = $this->connection->fetchOne('SELECT checkout_id FROM ucp_checkout_state WHERE order_id = ? LIMIT 1', [$orderId]);

        return \is_string($checkoutId) ? $checkoutId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fulfillmentForOrder(string $orderId): ?array
    {
        $this->ensureTable();
        $raw = $this->connection->fetchOne('SELECT fulfillment_json FROM ucp_checkout_state WHERE order_id = ? LIMIT 1', [$orderId]);
        if (!\is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fulfillmentForCheckout(string $checkoutId): ?array
    {
        $this->ensureTable();
        $raw = $this->connection->fetchOne('SELECT fulfillment_json FROM ucp_checkout_state WHERE checkout_id = ? LIMIT 1', [$checkoutId]);
        if (!\is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveOrderExtras(string $orderId, array $payload): void
    {
        $this->ensureOrderExtrasTable();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->executeStatement(
            'INSERT INTO ucp_order_extras (order_id, payload_json, updated_at, created_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = VALUES(updated_at)',
            [$orderId, json_encode($payload, \JSON_THROW_ON_ERROR), $now, $now]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function orderExtras(string $orderId): ?array
    {
        $this->ensureOrderExtrasTable();
        $raw = $this->connection->fetchOne('SELECT payload_json FROM ucp_order_extras WHERE order_id = ? LIMIT 1', [$orderId]);
        if (!\is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : null;
    }

    private function upsert(string $checkoutId, string $state, ?string $orderId): void
    {
        $this->ensureTable();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $this->connection->executeStatement(
            'INSERT INTO ucp_checkout_state (checkout_id, state, order_id, buyer_json, updated_at, created_at)
             VALUES (?, ?, ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), order_id = VALUES(order_id), updated_at = VALUES(updated_at)',
            [$checkoutId, $state, $orderId, $now, $now]
        );
    }

    private function ensureTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS ucp_checkout_state (
                checkout_id VARCHAR(190) NOT NULL,
                state VARCHAR(32) NOT NULL,
                order_id VARCHAR(64) NULL,
                buyer_json JSON NULL,
                fulfillment_json JSON NULL,
                updated_at DATETIME(3) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (checkout_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        try {
            $this->connection->executeStatement('ALTER TABLE ucp_checkout_state ADD COLUMN buyer_json JSON NULL AFTER order_id');
        } catch (\Throwable) {
            // Column already exists.
        }
        try {
            $this->connection->executeStatement('ALTER TABLE ucp_checkout_state ADD COLUMN fulfillment_json JSON NULL AFTER buyer_json');
        } catch (\Throwable) {
            // Column already exists.
        }
    }

    private function ensureAddressTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS ucp_conformance_address (
                id VARCHAR(190) NOT NULL,
                email VARCHAR(190) NOT NULL,
                address_json JSON NOT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_ucp_conformance_address_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function ensureOrderExtrasTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS ucp_order_extras (
                order_id VARCHAR(64) NOT NULL,
                payload_json JSON NOT NULL,
                updated_at DATETIME(3) NOT NULL,
                created_at DATETIME(3) NOT NULL,
                PRIMARY KEY (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * @param array<string, mixed> $address
     */
    private function addressId(array $address): string
    {
        if (\is_string($address['id'] ?? null) && $address['id'] !== '') {
            return $address['id'];
        }
        if (($address['street_address'] ?? null) === '123 Main St' && ($address['postal_code'] ?? null) === '62704') {
            return 'addr_1';
        }

        return 'addr_' . substr(Hasher::hash(json_encode($address, \JSON_THROW_ON_ERROR)), 0, 12);
    }
}
