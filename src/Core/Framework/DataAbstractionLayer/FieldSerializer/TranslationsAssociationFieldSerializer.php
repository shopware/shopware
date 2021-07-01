<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingSystemTranslationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingTranslationLanguageException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class TranslationsAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtractor;

    public function __construct(
        WriteCommandExtractor $writeExtractor
    ) {
        $this->writeExtractor = $writeExtractor;
    }

    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof TranslationsAssociationField) {
            throw new InvalidSerializerFieldException(TranslationsAssociationField::class, $field);
        }

        $key = $field->getPropertyName();
        $value = $data[$key] ?? null;

        $path = $parameters->getPath() . '/' . $key;
        /** @var EntityTranslationDefinition $referenceDefinition */
        $referenceDefinition = $field->getReferenceDefinition();

        if ($value === null) {
            $languageId = $parameters->getContext()->getContext()->getLanguageId();
            $clonedParams = $parameters->cloneForSubresource(
                $referenceDefinition,
                $path . '/' . $languageId
            );

            $data[$key] = [
                $languageId => $this->writeExtractor->normalizeSingle($referenceDefinition, [], $clonedParams),
            ];

            return $data;
        }

        $languageField = $referenceDefinition->getFields()->getByStorageName($field->getLanguageField());
        $languagePropName = $languageField->getPropertyName();

        foreach ($value as $identifier => $fields) {
            /* Supported formats:
                translations => [['property' => 'translation', 'languageId' => '{languageUuid}']] -> skip
                translations => [['property' => 'translation', 'language' => ['id' => {languageUuid}'] ]] -> skip
                translations => ['{languageUuid}' => ['property' => 'translation']] -> skip
                translations => ['en-GB' => ['property' => 'translation']] -> proceed and use localeLanguageResolver
            */
            if (is_numeric($identifier) || Uuid::isValid($identifier)) {
                continue;
            }

            $languageId = $parameters->getContext()->getLanguageId($identifier);

            if ($languageId === null) {
                unset($value[$identifier]);

                continue;
            }

            if (!isset($value[$languageId])) {
                $value[$languageId] = $fields;
            } else {
                $value[$languageId] = array_merge($value[$identifier], $value[$languageId]);
            }

            unset($value[$identifier]);
        }

        $translations = [];
        foreach ($value as $keyValue => $subResources) {
            if (!\is_array($subResources)) {
                throw new ExpectedArrayException($path);
            }

            // See above for Supported formats
            $languageId = $keyValue;
            if (is_numeric($languageId) && $languageId >= 0 && $languageId < \count($value)) {
                // languageId is a property of $subResources. Also see formats above
                if (isset($subResources[$languagePropName])) {
                    $languageId = $subResources[$languagePropName];
                } elseif (isset($subResources['language']['id'])) {
                    $languageId = $subResources['language']['id'];
                } else {
                    throw new MissingTranslationLanguageException($path, $keyValue);
                }
            } elseif ($languagePropName) {
                // the key is the language id, also write it into $subResources
                $subResources[$languagePropName] = $languageId;
            }
            $translations[$languageId] = $subResources;
        }

        foreach ($translations as $languageId => $translation) {
            $clonedParams = $parameters->cloneForSubresource(
                $referenceDefinition,
                $path . '/' . $languageId
            );

            $translation = $this->writeExtractor->normalizeSingle($referenceDefinition, $translation, $clonedParams);

            $translations[$languageId] = $translation;
        }

        $data[$key] = $translations;

        return $data;
    }

    /**
     * @throws ExpectedArrayException
     * @throws InvalidSerializerFieldException
     * @throws MissingSystemTranslationException
     * @throws MissingTranslationLanguageException
     */
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof TranslationsAssociationField) {
            throw new InvalidSerializerFieldException(TranslationsAssociationField::class, $field);
        }

        $value = $data->getValue();

        if ($value === null) {
            $value = [
                $parameters->getContext()->getContext()->getLanguageId() => [],
            ];
            $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

            return $this->map($field, $data, $parameters, $existence);
        }

        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        return $this->map($field, $data, $parameters, $existence);
    }

    /**
     * @throws DecodeByHydratorException
     *
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     * @never
     */
    public function decode(Field $field, /*?string */$value): void
    {
        throw new DecodeByHydratorException($field);
    }

    /**
     * @throws ExpectedArrayException
     * @throws MissingSystemTranslationException
     * @throws MissingTranslationLanguageException
     */
    protected function map(
        TranslationsAssociationField $field,
        KeyValuePair $data,
        WriteParameterBag $parameters,
        EntityExistence $existence
    ): \Generator {
        $key = $data->getKey();
        $value = $data->getValue();
        $path = $parameters->getPath() . '/' . $key;

        /** @var EntityTranslationDefinition $referenceDefinition */
        $referenceDefinition = $field->getReferenceDefinition();

        if (!\is_array($value)) {
            throw new ExpectedArrayException($path);
        }

        foreach ($value as $languageId => $translation) {
            $clonedParams = $parameters->cloneForSubresource(
                $referenceDefinition,
                $path . '/' . $languageId
            );
            $clonedParams->setCurrentWriteLanguageId($languageId);

            $this->writeExtractor->extract($translation, $clonedParams);
        }

        // the validation is only required for new entities
        if ($existence->exists()) {
            return;
        }

        $languageIds = array_keys($value);
        // the translation in the system language is always required for new entities,
        // if there is at least one required translated field
        if ($referenceDefinition->hasRequiredField() && !\in_array(Defaults::LANGUAGE_SYSTEM, $languageIds, true)) {
            throw new MissingSystemTranslationException($path . '/' . Defaults::LANGUAGE_SYSTEM);
        }

        yield from [];
    }
}
