<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\ContextGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Backfills the implicit capability permissions (tax provider / checkout gateway / context gateway)
 * for apps installed before these permissions existed.
 *
 * Apps that are already fully consented receive the permission as granted, so their handlers
 * keep working without regression. Apps still pending consent (services awaiting consent)
 * receive it as requested, which keeps their handler gated and closes the gap where a
 * not-yet-consented service still received PII.
 *
 * @internal
 */
#[Package('framework')]
class Migration1783691346AddAppCapabilityPermissions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783691346;
    }

    public function update(Connection $connection): void
    {
        $capabilities = $this->collectCapabilities($connection);

        if ($capabilities === []) {
            return;
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT LOWER(HEX(a.id)) AS app_id, a.requested_privileges, LOWER(HEX(a.acl_role_id)) AS acl_role_id, r.privileges
             FROM app a
             INNER JOIN acl_role r ON r.id = a.acl_role_id
             WHERE a.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_keys($capabilities))],
            ['ids' => ArrayParameterType::BINARY]
        );

        foreach ($rows as $row) {
            $markers = $capabilities[$row['app_id']];
            $requested = $this->decode($row['requested_privileges']);
            $granted = $this->decode($row['privileges']);

            // An app is fully consented when nothing is still requested; otherwise it is pending.
            // Grant the marker for consented apps, keep it requested for pending ones.
            if ($requested === []) {
                $new = array_values(array_unique([...$granted, ...$markers]));

                if ($new === $granted) {
                    continue;
                }

                $connection->executeStatement(
                    'UPDATE `acl_role` SET `privileges` = :privileges WHERE id = :id',
                    ['privileges' => json_encode($new, \JSON_THROW_ON_ERROR), 'id' => Uuid::fromHexToBytes($row['acl_role_id'])]
                );

                continue;
            }

            $new = array_values(array_unique([...$requested, ...$markers]));

            if ($new === $requested) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE `app` SET `requested_privileges` = :privileges WHERE id = :id',
                ['privileges' => json_encode($new, \JSON_THROW_ON_ERROR), 'id' => Uuid::fromHexToBytes($row['app_id'])]
            );
        }
    }

    /**
     * @return array<string, list<string>> hex app id => capability markers to add
     */
    private function collectCapabilities(Connection $connection): array
    {
        $map = [];

        foreach ($connection->fetchFirstColumn('SELECT DISTINCT LOWER(HEX(app_id)) FROM tax_provider WHERE app_id IS NOT NULL') as $id) {
            $map[$id][] = Tax::PERMISSION;
        }

        foreach ($connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM app WHERE checkout_gateway_url IS NOT NULL') as $id) {
            $map[$id][] = CheckoutGateway::PERMISSION;
        }

        foreach ($connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM app WHERE context_gateway_url IS NOT NULL') as $id) {
            $map[$id][] = ContextGateway::PERMISSION;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function decode(?string $privileges): array
    {
        if ($privileges === null || $privileges === '') {
            return [];
        }

        /** @var list<string> $decoded */
        $decoded = json_decode($privileges, true, flags: \JSON_THROW_ON_ERROR) ?: [];

        return $decoded;
    }
}
