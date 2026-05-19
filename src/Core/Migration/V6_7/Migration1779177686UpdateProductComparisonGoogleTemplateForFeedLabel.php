<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class Migration1779177686UpdateProductComparisonGoogleTemplateForFeedLabel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779177686;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $fixturePath = __DIR__ . '/../Fixtures/productComparison-export-profiles/';
        $templateOld = $filesystem->readFile($fixturePath . 'next-39314/google_new.xml.twig');
        $templateNew = $filesystem->readFile($fixturePath . 'new-template-google.xml.twig');

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
