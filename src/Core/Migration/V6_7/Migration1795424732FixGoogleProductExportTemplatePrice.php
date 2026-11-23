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
#[Package('inventory')]
class Migration1795424732FixGoogleProductExportTemplatePrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795424732;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();
        $fixtures = __DIR__ . '/../Fixtures/productComparison-export-profiles/issue-18322/';

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach (['google', 'agentic_google'] as $name) {
            $connection->update(
                'product_export',
                [
                    'body_template' => $filesystem->readFile($fixtures . $name . '_new.xml.twig'),
                    'updated_at' => $now,
                ],
                ['body_template' => $filesystem->readFile($fixtures . $name . '_old.xml.twig')],
            );
        }
    }
}
