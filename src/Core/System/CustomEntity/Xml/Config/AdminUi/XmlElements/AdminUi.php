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
#[Package('buyers-experience')]
final class AdminUi extends ConfigXmlElement
{
    /**
     * @var array<string, Entity>
     */
    protected array $entities;

    /**
     * @return array<string, Entity>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    protected static function parse(\DOMElement $element): array
    {
        $entities = [];
        foreach ($element->getElementsByTagName('entity') as $entity) {
            $entity = Entity::fromXml($entity);
            $entities[$entity->getName()] = $entity;
        }

        return ['entities' => $entities];
    }
}
