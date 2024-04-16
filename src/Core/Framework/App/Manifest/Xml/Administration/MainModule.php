<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Administration;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class MainModule extends XmlElement
{
    protected string $source;

    public function getSource(): string
    {
        return $this->source;
    }

    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseAttributes($element);
    }
}
