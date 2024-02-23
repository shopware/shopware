<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1564385960ThemeAddActiveFlag extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1564385960;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'theme',
            'active',
            'TINYINT(1)',
            false,
            '1'
        );

        $connection->executeStatement('
            UPDATE `media_default_folder` SET `association_fields` = \'[\"media\"]\' WHERE `entity` = \'theme\';
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
