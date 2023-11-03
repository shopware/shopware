<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class SingleSelectField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    protected const COMPONENT_NAME = 'sw-single-select';

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    /**
     * @var array<string, string>
     */
    protected array $options;

    /**
     * @return array<string, string>
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    protected function toEntityArray(): array
    {
        $options = [];

        foreach ($this->options as $key => $names) {
            $options[] = [
                'label' => $names,
                'value' => $key,
            ];
        }

        return [
            'type' => CustomFieldTypes::SELECT,
            'config' => [
                'placeholder' => $this->placeholder,
                // use $this so child classes can override the const
                'componentName' => $this::COMPONENT_NAME,
                'customFieldType' => 'select',
                'options' => $options,
            ],
        ];
    }
}
