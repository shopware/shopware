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
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $event;

    protected bool $onlyLiveVersion = false;

    /**
     * @param array{name: string, url: string, event: string, onlyLiveVersion?: bool} $data
     */
    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            /** @phpstan-ignore-next-line  */
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

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

    /**
     * @return array{name: string, url: string, event: string, onlyLiveVersion?: bool}
     */
    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            \assert($attribute instanceof \DOMAttr);
            $values[$attribute->name] = XmlReader::phpize($attribute->value);
        }

        /** @var array{name: string, url: string, event: string, onlyLiveVersion?: bool} $values */

        return $values;
    }
}
