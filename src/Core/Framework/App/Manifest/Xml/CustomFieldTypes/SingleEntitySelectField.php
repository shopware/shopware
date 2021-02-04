<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class SingleEntitySelectField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    protected const COMPONENT_NAME = 'sw-entity-single-select';

    /**
     * @var array
     */
    protected $placeholder = [];

    /**
     * @var string
     */
    protected $entity;

    protected function __construct(array $data)
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

    public function getEntity(): string
    {
        return $this->entity;
    }

    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::ENTITY,
            'config' => [
                'entity' => $this->entity,
                'placeholder' => $this->placeholder,
                // use $this so child classes can override the const
                'componentName' => $this::COMPONENT_NAME,
                'customFieldType' => 'select',
            ],
        ];
    }
}
