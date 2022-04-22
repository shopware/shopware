<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Permissions extends XmlElement
{
    protected array $permissions;

    protected array $additionalPrivileges;

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parsePermissions($element));
    }

    /**
     * @param array $permissions permissions as array indexed by resource
     * @param string[] $additionalPrivileges additional non-CRUD privileges as flat list
     */
    public static function fromArray(array $permissions, array $additionalPrivileges = []): self
    {
        return new self([
            'permissions' => $permissions,
            'additionalPrivileges' => $additionalPrivileges,
        ]);
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function add(array $permissions): void
    {
        foreach ($permissions as $resource => $privileges) {
            $this->permissions[$resource] = $privileges;
        }
    }

    public function getAdditionalPrivileges(): array
    {
        return $this->additionalPrivileges;
    }

    public function asParsedPrivileges(): array
    {
        return $this->generatePrivileges();
    }

    private static function parsePermissions(\DOMElement $element): array
    {
        $permissions = [];
        $additionalPrivileges = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
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
            $newPrivileges = array_map(static function (string $privilege) use ($resource): string {
                return $resource . ':' . $privilege;
            }, $privileges);

            $privilegeValues = array_merge($privilegeValues, $newPrivileges);
        }

        return array_merge($privilegeValues, $this->additionalPrivileges);
    }
}
