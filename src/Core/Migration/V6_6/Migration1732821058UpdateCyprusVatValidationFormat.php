<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1732821058UpdateCyprusVatValidationFormat extends MigrationStep
{
    private const VAT_PATTERNS = [
        'CY' => 'CY\d{8}[A-Z]'
    ];

    public function getCreationTimestamp(): int
    {
        return 1732821058;
    }

    public function update(Connection $connection): void
    {
        $update = new RetryableQuery(
            $connection,
            $connection->prepare('UPDATE country SET vat_id_pattern = :pattern WHERE iso = :iso')
        );

        foreach (self::VAT_PATTERNS as $key => $pattern) {
            $update->execute([
                'pattern' => $pattern,
                'iso' => $key,
            ]);
        }
    }
}