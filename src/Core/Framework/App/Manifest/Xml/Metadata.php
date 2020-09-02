<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

class Metadata extends XmlElement
{
    /**
     * @var array
     */
    protected $label;

    /**
     * @var array
     */
    protected $description;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var string
     */
    protected $copyright;

    /**
     * @var string|null
     */
    protected $license;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string|null
     */
    protected $icon;

    /**
     * @var string|null
     */
    protected $privacy;

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

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getDescription(): array
    {
        return $this->description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getCopyright(): string
    {
        return $this->copyright;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getPrivacy(): ?string
    {
        return $this->privacy;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (in_array($child->tagName, ['label', 'description'], true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            $values[$child->tagName] = $child->nodeValue;
        }

        return $values;
    }
}
