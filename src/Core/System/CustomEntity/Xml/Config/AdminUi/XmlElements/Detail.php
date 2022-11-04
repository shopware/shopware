<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the XML detail element
 *
 * admin-ui > entity > detail
 *
 * @internal
 */
class Detail extends CustomEntityFlag
{
    private const MAPPING = [
        'tabs' => Tabs::class,
    ];

    protected CustomEntityFlag $tabs;

    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    public function getTabs(): CustomEntityFlag
    {
        return $this->tabs;
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

        $values[$child->tagName] = $class::fromXml($child);

        return $values;
    }
}
