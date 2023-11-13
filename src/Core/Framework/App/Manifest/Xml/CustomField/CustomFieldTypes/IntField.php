<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class IntField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    protected ?int $steps = null;

    protected ?int $min = null;

    protected ?int $max = null;

    /**
     * @return array<string, string>
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    public function getSteps(): ?int
    {
        return $this->steps;
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    protected function toEntityArray(): array
    {
        $entityArray = [
            'type' => CustomFieldTypes::INT,
            'config' => [
                'type' => 'number',
                'placeholder' => $this->placeholder,
                'componentName' => 'sw-field',
                'customFieldType' => 'number',
                'numberType' => 'int',
            ],
        ];

        if ($this->max !== null) {
            $entityArray['config']['max'] = $this->max;
        }

        if ($this->min !== null) {
            $entityArray['config']['min'] = $this->min;
        }

        if ($this->steps !== null) {
            $entityArray['config']['step'] = $this->steps;
        }

        return $entityArray;
    }
}
