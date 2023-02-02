<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class TextField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array
     */
    protected $placeholder = [];

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): CustomFieldType
    {
        return new self(self::parse($element, self::TRANSLATABLE_FIELDS));
    }

    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'type' => 'text',
                'placeholder' => $this->placeholder,
                'componentName' => 'sw-field',
                'customFieldType' => 'text',
            ],
        ];
    }
}
