<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Store\Helper\PermissionCategorization;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 */
class PermissionCollection extends StoreCollection
{
    public function __construct(iterable $elements = [])
    {
        if (!empty($elements) && $this->hasNoPermissionStructElements((array) $elements)) {
            $elements = $this->generatePrivileges((array) $elements);
        }

        $elements = array_unique((array) $elements, \SORT_REGULAR);

        parent::__construct($elements);
    }

    public function getCategorizedPermissions(): array
    {
        $permissionCollections = [];

        foreach (PermissionCategorization::getCategoryNames() as $category) {
            $categoryPermissions = $this->getPermissionsForCategory($category);

            if ($categoryPermissions->count() === 0) {
                continue;
            }

            $permissionCollections[$category] = $categoryPermissions;
        }

        return $permissionCollections;
    }

    protected function getExpectedClass(): ?string
    {
        return PermissionStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return PermissionStruct::fromArray($element);
    }

    private function getPermissionsForCategory(string $category): PermissionCollection
    {
        return $this->filter(static function (PermissionStruct $element) use ($category) {
            return PermissionCategorization::isInCategory($element->getEntity(), $category);
        });
    }

    private function generatePrivileges(array $permissions): array
    {
        foreach ($permissions as $permission) {
            if (!\array_key_exists($permission['operation'], AclRoleDefinition::PRIVILEGE_DEPENDENCE)) {
                continue;
            }

            $operations = AclRoleDefinition::PRIVILEGE_DEPENDENCE[$permission['operation']];

            foreach ($operations as $operation) {
                $dependentPermission = [
                    'entity' => $permission['entity'],
                    'operation' => $operation,
                ];

                if (!\in_array($dependentPermission, $permissions, true)) {
                    $permissions[] = $dependentPermission;
                }
            }
        }

        return $permissions;
    }

    private function hasNoPermissionStructElements(array $elements): bool
    {
        return empty(array_filter($elements, static function ($element) {
            return $element instanceof PermissionStruct;
        }));
    }
}
