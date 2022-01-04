<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class Entities extends XmlElement
{
    /**
     * @var Entity[]
     */
    protected $entities = [];

    public function __construct(array $entities)
    {
        $this->entities = $entities;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseEntities($element));
    }

    /**
     * @return Entity[]
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @return Entity[]
     */
    private static function parseEntities(\DOMElement $element): array
    {
        $entities = [];
        foreach ($element->getElementsByTagName('entity') as $entity) {
            $entities[] = Entity::fromXml($entity);
        }

        return $entities;
    }
}
