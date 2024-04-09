<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Helper\PermissionCategorization;

/**
 * @codeCoverageIgnore
 *
 * @template-extends StoreCollection<PermissionStruct>
 *
 * @phpstan-type PermissionArray array{entity: string, operation: AclRoleDefinition::PRIVILEGE_READ|AclRoleDefinition::PRIVILEGE_CREATE|AclRoleDefinition::PRIVILEGE_UPDATE|AclRoleDefinition::PRIVILEGE_DELETE}
 */
#[Package('checkout')]
class PermissionCollection extends StoreCollection
{
    /**
     * @param list<PermissionStruct>|list<PermissionArray> $elements
     */
    public function __construct(iterable $elements = [])
    {
        $elements = (array) $elements;
        if (!empty($elements) && $this->hasNoPermissionStructElements($elements)) {
            /** @phpstan-ignore-next-line PHPStan does not recognize, that $elements does not contain "PermissionStruct" instances at this point, which is checked by "hasNoPermissionStructElements" method */
            $elements = $this->generatePrivileges($elements);
        }

        $elements = array_unique($elements, \SORT_REGULAR);

        parent::__construct($elements);
    }

    /**
     * @return array<string, PermissionCollection>
     */
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

    /**
     * @param PermissionArray $element
     */
    protected function getElementFromArray(array $element): StoreStruct
    {
        return PermissionStruct::fromArray($element);
    }

    private function getPermissionsForCategory(string $category): PermissionCollection
    {
        return $this->filter(static fn (PermissionStruct $element) => PermissionCategorization::isInCategory($element->getEntity(), $category));
    }

    /**
     * @param array<PermissionArray> $permissions
     *
     * @return array<PermissionArray>
     */
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

    /**
     * @param list<PermissionStruct>|list<PermissionArray> $elements
     */
    private function hasNoPermissionStructElements(array $elements): bool
    {
        return empty(array_filter($elements, static fn ($element) => $element instanceof PermissionStruct));
    }
}
