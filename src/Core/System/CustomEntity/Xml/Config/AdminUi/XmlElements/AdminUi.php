<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * Represents the AdminUi configuration
 *
 * The config is located here Resources/config/admin-ui.xml
 *
 * @internal
 */
#[Package('content')]
final class AdminUi extends ConfigXmlElement
{
    /**
     * @param array<string, Entity> $entities
     */
    private function __construct(
        protected readonly array $entities,
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        $entities = [];
        foreach ($element->getElementsByTagName('entity') as $entity) {
            $entity = Entity::fromXml($entity);
            $entities[$entity->getName()] = $entity;
        }

        return new self($entities);
    }

    /**
     * @return array<string, Entity>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
