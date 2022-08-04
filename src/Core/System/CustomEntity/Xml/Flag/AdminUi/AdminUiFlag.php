<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Flag\Flag;

/**
 * @internal
 */
class AdminUiFlag extends Flag
{
    private const MAPPING = [
        'detail' => DetailConfig::class,
        'listing' => ListingConfig::class,
    ];

    protected string $technicalName = 'admin-ui';

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

        $values[$child->tagName] = $class::fromXml($child);

        return $values;
    }
}
