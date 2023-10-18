<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class SingleEntitySelectField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    protected const COMPONENT_NAME = 'sw-entity-single-select';

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    protected string $entity;

    protected ?string $labelProperty = null;

    /**
     * @return array<string, string>
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
