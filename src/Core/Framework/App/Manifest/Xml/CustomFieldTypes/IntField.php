<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class IntField extends CustomFieldType
{
    protected const TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array
     */
    protected $placeholder = [];

    /**
     * @var int|null
     */
    protected $steps;

    /**
     * @var int|null
     */
    protected $min;

    /**
     * @var int|null
     */
    protected $max;

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
