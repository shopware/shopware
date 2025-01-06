<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1731576063UpdateProductComparisonTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1731576063;
    }

    public function update(Connection $connection): void
    {
        $fixturePath = __DIR__ . '/../Fixtures/productComparison-export-profiles/next-39314/';
        $templateOld = file_get_contents($fixturePath . 'google_old.xml.twig');
        $templateNew = file_get_contents($fixturePath . 'google_new.xml.twig');

        $connection->update(
            'product_export',
            [
                'body_template' => $templateNew,
                'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'body_template' => $templateOld,
            ]
        );
    }
}
