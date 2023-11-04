<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1641289204FixProductComparisonGoogleShippingPriceDisplay extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1641289204;
    }

    public function update(Connection $connection): void
    {
        $old_template = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_old.xml.twig');
        $new_template = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_new.xml.twig');

        $connection->update(
            'product_export',
            ['body_template' => $new_template, 'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['body_template' => $old_template]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
