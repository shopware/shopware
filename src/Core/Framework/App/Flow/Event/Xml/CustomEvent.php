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
     * @var array<string>
     */
    protected array $aware = [];

    /**
     * @param array<int|string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string>
     */
    public function getAware(): array
    {
        return $this->aware;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        return parent::toArray($defaultLocale);
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    /**
     * @return CustomEventArrayType
     */
    private static function parse(\DOMElement $element): array
    {
        /** @var CustomEventArrayType $values */
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
