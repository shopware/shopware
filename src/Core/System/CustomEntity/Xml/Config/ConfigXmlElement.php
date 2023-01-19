<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @package content
 *
 * @internal
 */
abstract class ConfigXmlElement extends XmlElement
{
    abstract public static function fromXml(\DOMElement $element): self;

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }
}
