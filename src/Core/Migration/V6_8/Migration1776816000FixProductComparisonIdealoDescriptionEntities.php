<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1776816000FixProductComparisonIdealoDescriptionEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776816000;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();
        $fixturePath = __DIR__ . '/../Fixtures/productComparison-export-profiles/next-13664/';

        $templateUpdates = [
            [
                'old' => $filesystem->readFile($fixturePath . 'old-template-idealo.csv.twig'),
                'new' => $filesystem->readFile($fixturePath . 'new-template-idealo.csv.twig'),
            ],
            [
                'old' => $filesystem->readFile($fixturePath . 'old-template-idealo-37658.csv.twig'),
                'new' => $filesystem->readFile($fixturePath . 'new-template-idealo-37658.csv.twig'),
            ],
        ];

        foreach ($templateUpdates as $templateUpdate) {
            $connection->update(
                'product_export',
                [
                    'body_template' => $templateUpdate['new'],
                    'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
                ['body_template' => $templateUpdate['old']]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
