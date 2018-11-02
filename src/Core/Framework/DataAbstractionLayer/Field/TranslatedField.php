<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\System\Language\LanguageDefinition;

class TranslatedField extends Field
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $referencedClassName;

    /**
     * @var string
     */
    private $foreignClassName;

    /**
     * @var string
     */
    private $foreignFieldName;

    /**
     * @var StorageAware|Field
     */
    private $field;

    public function __construct(StorageAware $field)
    {
        /** @var StorageAware|Field $field */
        $field = $field;

        $this->field = $field;
        $this->storageName = $field->getStorageName();
        $this->foreignClassName = LanguageDefinition::class;
        $this->foreignFieldName = 'id';

        parent::__construct($field->getPropertyName());
    }

    /**
     * @return string
     */
    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return string
     */
    public function getReferencedClassName(): string
    {
        return $this->referencedClassName;
    }

    public function getExtractPriority(): int
    {
        return 100;
    }

    public function getField(): StorageAware
    {
        return $this->field;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (\is_array($value)) {
            $isNumeric = \count(array_diff($value, range(0, \count($value)))) === 0;

            if ($isNumeric) {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ],
                    ];
                }
            } else {
                foreach ($value as $translationKey => $translationValue) {
                    yield 'translations' => [
                        $translationKey => [
                            $key => $translationValue,
                        ],
                    ];
                }
            }

            return;
        }

        // load from write context the default language
        yield 'translations' => [
            $this->writeContext->get($this->foreignClassName, $this->foreignFieldName) => [
                $key => $value,
            ],
        ];
    }
}
