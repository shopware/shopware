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
     * @var list<CustomFieldSet>
     */
    protected $customFieldSets = [];

    /**
     * @return list<CustomFieldSet>
     */
    public function getCustomFieldSets(): array
    {
        return $this->customFieldSets;
    }

    protected static function parse(\DOMElement $element): array
    {
        $customFieldSets = [];
        foreach ($element->getElementsByTagName('custom-field-set') as $customFieldSet) {
            $customFieldSets[] = CustomFieldSet::fromXml($customFieldSet);
        }

        return ['customFieldSets' => $customFieldSets];
    }
}
