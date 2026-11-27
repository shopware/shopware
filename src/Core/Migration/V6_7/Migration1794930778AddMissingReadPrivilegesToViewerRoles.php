<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1794930778AddMissingReadPrivilegesToViewerRoles extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'category.viewer' => [
            'sales_channel_domain:read',
        ],
        'cms.viewer' => [
            'cms_slot_translation:read',
        ],
        // The landing page viewer embeds the CMS viewer privileges without storing the `cms.viewer` key
        'landing_page.viewer' => [
            'cms_slot_translation:read',
        ],
        'theme.viewer' => [
            'sales_channel_type:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1794930778;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
