<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Setup extends XmlElement
{
    /**
     * @var string
     */
    protected $registrationUrl;

    /**
     * @var string|null
     */
    protected $secret;

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    public function getRegistrationUrl(): string
    {
        return $this->registrationUrl;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[$child->tagName] = $child->nodeValue;
        }

        return $values;
    }
}
