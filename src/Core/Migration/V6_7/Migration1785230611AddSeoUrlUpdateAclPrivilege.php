<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1785230611AddSeoUrlUpdateAclPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'product.editor' => [
            'seo_url:update',
        ],
        'category.editor' => [
            'seo_url:update',
        ],
        'landing_page.editor' => [
            'seo_url:update',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1785230611;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
