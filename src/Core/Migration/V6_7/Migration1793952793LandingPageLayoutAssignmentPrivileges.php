<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1793952793LandingPageLayoutAssignmentPrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793952793;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, [
            'experience_studio.viewer' => [
                'landing_page:read',
            ],
            'experience_studio.editor' => [
                'landing_page_content_layout:create',
                'landing_page_content_layout:update',
                'landing_page_content_layout:delete',
            ],
        ]);
    }
}
