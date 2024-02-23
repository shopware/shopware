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
class Migration1563785071AddThemeHelpText extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1563785071;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'theme_translation',
            'help_texts',
            'JSON',
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
