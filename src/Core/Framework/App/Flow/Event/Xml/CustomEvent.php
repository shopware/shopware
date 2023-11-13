<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Event\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type CustomEventArrayType array{name: string|null, aware?: array<int, string|null>, requirements?: array<int, string|null>}
 */
#[Package('core')]
class CustomEvent extends XmlElement
{
    public const REQUIRED_FIELDS = [
        'name',
    ];

    protected string $name;

    /**
     * @var list<string>
     */
    protected array $aware = [];

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getAware(): array
    {
        return $this->aware;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->nodeName === 'aware') {
                $values['aware'][] = $child->nodeValue;

                continue;
            }

            if ($child->nodeName === 'name') {
                $values['name'] = $child->nodeValue;
            }
        }

        return $values;
    }
}
