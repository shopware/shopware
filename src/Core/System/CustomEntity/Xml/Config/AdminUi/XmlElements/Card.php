<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the XML card element
 *
 * admin-ui > entity > detail > tabs > tab > card
 *
 * @internal
 */
class Card extends CustomEntityFlag
{
    private const MAPPING = [
        'field' => CardField::class,
    ];

    /**
     * @var CardField[]
     */
    protected array $fields;

    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    /**
     * @return CardField[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function parseChild(\DOMElement $child, array $values): array
    {
        /** @var CustomEntityFlag|null $class */
        $class = self::MAPPING[$child->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(\sprintf('Flag type "%s" not found', $child->tagName));
        }

        $values['fields'][] = $class::fromXml($child);

        return $values;
    }
}
