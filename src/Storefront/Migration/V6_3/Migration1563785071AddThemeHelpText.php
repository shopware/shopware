<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1563785071AddThemeHelpText extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563785071;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'theme_translation',
            column: 'help_texts',
            type: 'JSON',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
