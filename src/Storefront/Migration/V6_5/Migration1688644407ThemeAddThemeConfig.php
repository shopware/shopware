<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1688644407ThemeAddThemeConfig extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1688644407;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'theme',
            'theme_json',
            'JSON',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
