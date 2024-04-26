<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Permission;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Permissions extends XmlElement
{
    /**
     * CRUD permissions in the format
     * [
     *      ['customer' => ['read', 'update']],
     *      ['sales_channel' => ['read', 'delete']],
     *      ['category' => ['read']],
     * ]
     *
     * @var array<string, list<string>>
     */
    protected array $permissions;

    /**
     * @var list<string>
     */
    protected array $additionalPrivileges = [];

    /**
     * @return array<string, list<string>>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param array<string, list<string>> $permissions
     */
    public function add(array $permissions): void
    {
        foreach ($permissions as $resource => $privileges) {
            $this->permissions[$resource] = $privileges;
        }
    }

    /**
     * @return list<string>
     */
    public function getAdditionalPrivileges(): array
    {
        return $this->additionalPrivileges;
    }

    /**
     * Applies CRUD privilege dependencies (e.g. "update" requires "read") and formats the permissions to
     * [
     *     'customer:read',
     *     'customer:update',
     *     'sales_channel:read',
     *     'sales_channel:delete',
     *     'category:read',
     * ]
     *
     * @return array<string>
     */
    public function asParsedPrivileges(): array
    {
        return $this->generatePrivileges();
    }

    /**
     * @return array{permissions: array<string, list<string>>, additionalPrivileges: list<string>}
     */
    protected static function parse(\DOMElement $element): array
    {
        $permissions = [];
        $additionalPrivileges = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->nodeValue === null) {
                continue;
            }

            if ($child->tagName === 'permission') {
                $additionalPrivileges[] = $child->nodeValue;

                continue;
            }

            $permissions[$child->nodeValue][] = $child->tagName;
        }

        return [
            'permissions' => $permissions,
            'additionalPrivileges' => $additionalPrivileges,
        ];
    }

    /**
     * @return array<string>
     */
    private function generatePrivileges(): array
    {
        $grantedPrivileges = array_map(static function (array $privileges): array {
            $grantedPrivileges = [];

            foreach ($privileges as $privilege) {
                $grantedPrivileges[] = $privilege;
                $grantedPrivileges = array_merge($grantedPrivileges, AclRoleDefinition::PRIVILEGE_DEPENDENCE[$privilege]);
            }

            return array_unique($grantedPrivileges);
        }, $this->permissions);

        $privilegeValues = [];
        foreach ($grantedPrivileges as $resource => $privileges) {
            $newPrivileges = array_map(static fn (string $privilege): string => $resource . ':' . $privilege, $privileges);

            $privilegeValues = [...$privilegeValues, ...$newPrivileges];
        }

        return array_merge($privilegeValues, $this->additionalPrivileges);
    }
}
