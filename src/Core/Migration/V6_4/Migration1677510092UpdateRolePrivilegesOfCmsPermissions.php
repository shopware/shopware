<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1677510092UpdateRolePrivilegesOfCmsPermissions extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'cms.viewer' => [
            'cms_page_translation:read',
            'cms_slot_translation:read',
        ],
        'cms.editor' => [
            'cms_page_translation:create',
            'cms_page_translation:update',
            'cms_slot_translation:create',
            'cms_slot_translation:update',
        ],

        // privileges using getPermissions to not directly depend on them

        'product.viewer' => [
            'cms_page_translation:read',
            'cms_slot_translation:read',
        ],

        'category.viewer' => [
            'cms_page_translation:read',
            'cms_slot_translation:read',
        ],
        'category.editor' => [
            'cms_page_translation:create',
            'cms_page_translation:update',
            'cms_slot_translation:create',
            'cms_slot_translation:update',
        ],

        'landing_page.viewer' => [
            'cms_page_translation:read',
            'cms_slot_translation:read',
        ],
        'landing_page.editor' => [
            'cms_page_translation:create',
            'cms_page_translation:update',
            'cms_slot_translation:create',
            'cms_slot_translation:update',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1677510092;
    }

    public function update(Connection $connection): void
    {
        $roles = $connection->fetchAllAssociative('SELECT * from `acl_role`');

        foreach ($roles as $role) {
            $currentPrivileges = \json_decode((string) $role['privileges'], true, 512, \JSON_THROW_ON_ERROR);
            $newPrivileges = array_values($this->fixRolePrivileges($currentPrivileges));

            if ($currentPrivileges === $newPrivileges) {
                continue;
            }

            $role['privileges'] = json_encode($newPrivileges, \JSON_THROW_ON_ERROR);
            $role['updated_at'] = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT);

            $connection->update('acl_role', $role, ['id' => $role['id']]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @param list<string> $rolePrivileges
     *
     * @return list<string>
     */
    private function fixRolePrivileges(array $rolePrivileges): array
    {
        foreach (self::NEW_PRIVILEGES as $key => $new) {
            if (\in_array($key, $rolePrivileges, true)) {
                $rolePrivileges = [...$rolePrivileges, ...$new];
            }
        }

        return array_unique($rolePrivileges);
    }
}
