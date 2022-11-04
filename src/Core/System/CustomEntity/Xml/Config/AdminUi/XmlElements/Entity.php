<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the XML entity element
 *
 * admin-ui > entity
 */
class Entity extends CustomEntityFlag
{
    private const MAPPING = [
        'listing' => Listing::class,
        'detail' => Detail::class,
    ];

    protected string $name;

    protected Listing $listing;

    protected Detail $detail;

    public function getDetail(): Detail
    {
        return $this->detail;
    }

    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getListing(): Listing
    {
        return $this->listing;
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
