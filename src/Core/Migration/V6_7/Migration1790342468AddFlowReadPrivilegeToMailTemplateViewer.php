<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1790342468AddFlowReadPrivilegeToMailTemplateViewer extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'mail_templates.viewer' => [
            'flow:read',
            'flow_sequence:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1790342468;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
