<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Permissions extends XmlElement
{
    private const PRIVILEGE_DEPENDENCE = [
        AclRoleDefinition::PRIVILEGE_READ => [],
        AclRoleDefinition::PRIVILEGE_CREATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_UPDATE => [AclRoleDefinition::PRIVILEGE_READ],
        AclRoleDefinition::PRIVILEGE_DELETE => [AclRoleDefinition::PRIVILEGE_READ],
    ];

    /**
     * @var array
     */
    protected $permissions;

    private function __construct(array $permissions)
    {
        $this->permissions = $permissions;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parsePermissions($element));
    }

    /**
     * @param array $permissions permissions as array indexed by resource
     */
    public static function fromArray(array $permissions): self
    {
        return new self($permissions);
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function asParsedPrivileges(): array
    {
        return $this->generatePrivileges();
    }

    private static function parsePermissions(\DOMElement $element): array
    {
        $permissions = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $permissions[$child->nodeValue][] = $child->tagName;
        }

        return $permissions;
    }

    private function generatePrivileges(): array
    {
        $grantedPrivileges = array_map(static function (array $privileges): array {
            $grantedPrivileges = [];

            foreach ($privileges as $privilege) {
                $grantedPrivileges[] = $privilege;
                $grantedPrivileges = array_merge($grantedPrivileges, self::PRIVILEGE_DEPENDENCE[$privilege]);
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

        return $privilegeValues;
    }
}
