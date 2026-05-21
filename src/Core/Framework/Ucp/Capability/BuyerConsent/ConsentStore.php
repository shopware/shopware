<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\BuyerConsent;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Persists buyer-consent snapshots per checkout token. Backed by
 * `ucp_buyer_consent`; each row is a JSON blob keyed by checkout id so the
 * platform can re-read its consent state across multiple update calls.
 *
 * @internal
 */
#[Package('framework')]
class ConsentStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $checkoutId): ?array
    {
        $row = $this->connection->fetchOne(
            'SELECT consent_json FROM ucp_buyer_consent WHERE checkout_id = ? LIMIT 1',
            [$checkoutId]
        );

        if (!\is_string($row)) {
            return null;
        }

        try {
            $decoded = json_decode($row, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function save(string $checkoutId, string $salesChannelId, array $snapshot): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');
        $json = json_encode($snapshot, \JSON_THROW_ON_ERROR);

        $exists = $this->connection->fetchOne(
            'SELECT id FROM ucp_buyer_consent WHERE checkout_id = ? LIMIT 1',
            [$checkoutId]
        );

        if ($exists !== false) {
            $this->connection->update(
                'ucp_buyer_consent',
                [
                    'consent_json' => $json,
                    'updated_at' => $now,
                ],
                ['id' => $exists]
            );

            return;
        }

        $this->connection->insert('ucp_buyer_consent', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'checkout_id' => $checkoutId,
            'consent_json' => $json,
            'created_at' => $now,
        ]);
    }
}
