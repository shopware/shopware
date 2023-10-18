<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Tax;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProvider extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'processUrl',
        'priority',
    ];

    protected string $identifier;

    protected string $name;

    protected string $processUrl;

    protected int $priority;

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProcessUrl(): string
    {
        return $this->processUrl;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = XmlUtils::phpize((string) $child->nodeValue);
        }

        return $values;
    }
}
