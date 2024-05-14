<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class Field extends XmlElement
{
    protected string $name;

    protected bool $storeApiAware;

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isStoreApiAware(): bool
    {
        return $this->storeApiAware;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseAttributes($element);
        $values += XmlParserUtils::parseChildren($element);

        return $values;
    }
}
