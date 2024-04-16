<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Administration;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Module extends XmlElement
{
    private const TRANSLATABLE_FIELDS = [
        'label',
    ];

    /**
     * @var array<string, string>
     */
    protected array $label;

    protected ?string $source = null;

    protected string $name;

    protected ?string $parent = null;

    protected int $position = 1;

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseAttributes($element);
        $values += XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);

        return $values;
    }
}
