<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Webhook;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Webhook extends XmlElement
{
    protected string $name;

    protected string $url;

    protected string $event;

    protected bool $onlyLiveVersion = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getOnlyLiveVersion(): bool
    {
        return $this->onlyLiveVersion;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $values[$attribute->name] = XmlReader::phpize($attribute->value);
        }

        return $values;
    }
}
