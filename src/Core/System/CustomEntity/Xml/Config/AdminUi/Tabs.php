<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * @internal
 */
class Tabs extends CustomEntityFlag
{
    private const MAPPING = [
        'tab' => Tab::class,
    ];

    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<int|string, mixed>
     */
    protected function parseChild(\DOMElement $child, array $values): array
    {
        /** @var CustomEntityFlag|null $class */
        $class = self::MAPPING[$child->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(sprintf('Flag type "%s" not found', $child->tagName));
        }

        $values[] = $class::fromXml($child);

        return $values;
    }
}
