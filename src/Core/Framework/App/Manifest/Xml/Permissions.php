<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Permissions extends XmlElement
{
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
}
