<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class SingleEntitySelectField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    protected const COMPONENT_NAME = 'sw-entity-single-select';

    /**
     * @var string[]
     */
    protected $placeholder = [];

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var string|null
     */
    protected $labelProperty;

    /**
     * @param array<string, mixed> $data
     */
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

    /**
     * @return string[]
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getLabelProperty(): ?string
    {
        return $this->labelProperty;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toEntityArray(): array
    {
        $entityArray = [
            'type' => CustomFieldTypes::ENTITY,
            'config' => [
                'entity' => $this->entity,
                'placeholder' => $this->placeholder,
                // use $this so child classes can override the const
                'componentName' => $this::COMPONENT_NAME,
                'customFieldType' => 'select',
            ],
        ];

        if ($this->labelProperty !== null) {
            $entityArray['config']['labelProperty'] = $this->labelProperty;
        }

        return $entityArray;
    }
}
