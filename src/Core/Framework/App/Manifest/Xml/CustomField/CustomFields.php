<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class CustomFields extends XmlElement
{
    /**
     * @var CustomFieldSet[]
     */
    protected $customFieldSets = [];

    private function __construct(array $customFieldSets)
    {
        $this->customFieldSets = $customFieldSets;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseCustomFieldSets($element));
    }

    /**
     * @return CustomFieldSet[]
     */
    public function getCustomFieldSets(): array
    {
        return $this->customFieldSets;
    }

    private static function parseCustomFieldSets(\DOMElement $element): array
    {
        $customFieldSets = [];
        foreach ($element->getElementsByTagName('custom-field-set') as $customFieldSet) {
            $customFieldSets[] = CustomFieldSet::fromXml($customFieldSet);
        }

        return $customFieldSets;
    }
}
