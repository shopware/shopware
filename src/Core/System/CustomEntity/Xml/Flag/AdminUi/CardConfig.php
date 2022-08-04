<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Flag\Flag;

/**
 * @internal
 */
class CardConfig extends Flag
{
    private const MAPPING = [
        'field' => CardFieldConfig::class,
    ];

    public static function fromXml(\DOMElement $element): Flag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function parseChild(\DOMElement $child, array $values): array
    {
        /** @var Flag|null $class */
        $class = self::MAPPING[$child->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(sprintf('Flag type "%s" not found', $child->tagName));
        }

        $values['fields'][] = $class::fromXml($child);

        return $values;
    }
}
