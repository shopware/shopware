<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1791274683ContentLayoutTranslatePrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'experience_studio.editor' => [
            'content_layout:translate',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1791274683;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
