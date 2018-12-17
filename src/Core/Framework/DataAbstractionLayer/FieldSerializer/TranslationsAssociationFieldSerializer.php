<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Exception\MissingRootTranslationException;
use Shopware\Core\System\Exception\MissingSystemTranslationException;

class TranslationsAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtractor;

    public function __construct(WriteCommandExtractor $writeExtractor)
    {
        $this->writeExtractor = $writeExtractor;
    }

    public function getFieldClass(): string
    {
        return TranslationsAssociationField::class;
    }

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
        /* @var TranslationsAssociationField $field */

        if ($value === null) {
            $value = [
                $parameters->getContext()->getContext()->getLanguageId() => [],
            ];
            $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

            return $this->map($field, $data, $parameters, $existence);
        }

        foreach ($value as $identifier => $fields) {
            /* multiple formats are supported.
                translations => [['property' => 'translation', 'languageId' => '{languageUuid}']] -> skip
                translations => ['{languageUuid}' => ['property' => 'translation']] -> skip
                translations => ['en_GB' => ['property' => 'translation']] -> proceed and use localeLanguageResolver
            */
            if (is_numeric($identifier) || Uuid::isValid($identifier)) {
                continue;
            }

            $languageId = $parameters->getContext()->getLanguageId($identifier);
            if (!$languageId) {
                throw new LanguageNotFoundException($identifier);
            }

            if (!isset($value[$languageId])) {
                $value[$languageId] = $fields;
            } else {
                $value[$languageId] = array_merge($value[$identifier], $value[$languageId]);
            }

            unset($value[$identifier]);
        }

        // move root translations before child translations
        $context = $parameters->getContext();
        uksort($value, function ($a, $b) use ($context) {
            $aIsRoot = $context->isRootLanguage($a);
            $bIsRoot = $context->isRootLanguage($b);

            return $bIsRoot <=> $aIsRoot;
        });

        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        return $this->map($field, $data, $parameters, $existence);
    }

    public function decode(Field $field, $value)
    {
        throw new DecodeByHydratorException($field);
    }

    protected function map(TranslationsAssociationField $field, KeyValuePair $data, WriteParameterBag $parameters, EntityExistence $existence): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, \count($value) - 1);

        $languageIds = [];
        foreach ($value as $keyValue => $subResources) {
            if (!\is_array($subResources)) {
                throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
            }

            $languageId = $keyValue;
            if ($isNumeric) {
                // languageId is a property of $subResources. Also see formats above
                $languageId = $subResources[$field->getReferenceField()];
            } elseif ($field->getReferenceField()) {
                // the key is the language id, also write it into $subResources
                $subResources[$field->getReferenceField()] = $languageId;
            }
            $languageIds[] = $languageId;

            $clonedParams = $parameters->cloneForSubresource(
                $field->getReferenceClass(),
                $parameters->getPath() . '/' . $key . '/' . $languageId
            );
            $clonedParams->setCurrentWriteLanguageId($languageId);

            $this->writeExtractor->extract($subResources, $clonedParams);
        }

        // the validation is only required for new entities
        if ($existence->exists()) {
            return;
        }

        // the translation in the system language is always required for new entities
        if (!\in_array(Defaults::LANGUAGE_SYSTEM, $languageIds, true)) {
            $path = $parameters->getPath() . '/' . $key . '/' . Defaults::LANGUAGE_SYSTEM;
            throw new MissingSystemTranslationException($path);
        }

        // if you insert a translation for a child language, you need to include one for the child's root too.
        foreach ($languageIds as $id) {
            $isChild = !$parameters->getContext()->isRootLanguage($id);
            $rootId = $parameters->getContext()->getRootLanguageId($id);

            if ($isChild && !\in_array($rootId, $languageIds, true)) {
                $path = $parameters->getPath() . '/' . $key . '/' . $rootId;
                throw new MissingRootTranslationException($rootId, $id, $path);
            }
        }

        // yield nothing. There has to be one yield for php to type check
        return;
        yield __CLASS__ => __METHOD__;
    }
}
