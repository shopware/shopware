<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class TranslatedFieldSerializer implements FieldSerializerInterface
{
    public function getFieldClass(): string
    {
        return TranslatedField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof TranslatedField) {
            throw new InvalidSerializerFieldException(TranslatedField::class, $field);
        }
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
        /* @var TranslatedField $field */
        yield 'translations' => [
            $parameters->getContext()->get($field->getForeignClassName(), $field->getForeignFieldName()) => [
                $key => $value,
            ],
        ];
    }

    public function decode(Field $field, $value)
    {
        return $value === null ? null : (string) $value;
    }
}
