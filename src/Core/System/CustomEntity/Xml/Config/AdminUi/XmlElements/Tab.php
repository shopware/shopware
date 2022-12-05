<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the XML tab element
 *
 * admin-ui > entity > detail > tabs > tab
 *
 * @package content
 *
 * @internal
 */
class Tab extends CustomEntityFlag
{
    private const MAPPING = [
        'card' => Card::class,
    ];

    protected string $name;

    /**
     * @var Card[]
     */
    protected array $cards;

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
     * @return Card[]
     */
    public function getCards(): array
    {
        return $this->cards;
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

        $values['cards'][] = $class::fromXml($child);

        return $values;
    }
}
