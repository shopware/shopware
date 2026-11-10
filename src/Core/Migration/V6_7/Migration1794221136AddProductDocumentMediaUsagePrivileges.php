<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1794221136AddProductDocumentMediaUsagePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794221136;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, [
            'media.viewer' => [
                'product_document:read',
                'product_document_translation:read',
                'property_group_option:read',
                'property_group:read',
            ],
        ]);
    }
}
