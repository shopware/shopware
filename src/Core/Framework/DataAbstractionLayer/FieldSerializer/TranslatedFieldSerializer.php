<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class TranslatedFieldSerializer implements FieldSerializerInterface
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof TranslatedField) {
            throw new InvalidSerializerFieldException(TranslatedField::class, $field);
        }
        $key = $data->getKey();
        $value = $data->getValue();

        $translatedField = EntityDefinitionQueryHelper::getTranslatedField($parameters->getDefinition(), $field);

        if (\is_array($value) && $translatedField instanceof JsonField === false) {
            foreach ($value as $translationKey => $translationValue) {
                yield 'translations' => [
                    $translationKey => [
                        $key => $translationValue,
                    ],
                ];
            }

            return;
        }

        // use the default language from the context
        /* @var TranslatedField $field */
        yield 'translations' => [
            $parameters->getContext()->getContext()->getLanguageId() => [
                $key => $value,
            ],
        ];
    }

    public function decode(Field $field, $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
