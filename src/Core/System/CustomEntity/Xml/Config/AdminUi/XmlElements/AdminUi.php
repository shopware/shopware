<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the AdminUi configuration
 *
 * The config is located here Resources/config/admin-ui.xml
 */
class AdminUi extends CustomEntityFlag
{
    private const MAPPING = [
        'entity' => Entity::class,
    ];

    /**
     * @var array<string, Entity>
     */
    protected array $entities;

    public static function fromXml(\DOMElement $element): self
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    /**
     * @return array<string, Entity>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function parseChild(\DOMElement $child, array $values): array
    {
        /** @var Entity|null $class */
        $class = self::MAPPING[$child->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(\sprintf('Flag type "%s" not found', $child->tagName));
        }

        if ($child->tagName === 'entity') {
            /** @var Entity $entity */
            $entity = $class::fromXml($child);
            $values['entities'][$entity->getName()] = $entity;
        } else {
            $values[$child->tagName] = $class::fromXml($child);
        }

        return $values;
    }
}
