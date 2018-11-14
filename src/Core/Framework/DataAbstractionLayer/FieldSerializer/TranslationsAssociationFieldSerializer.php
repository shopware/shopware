<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Locale\LocaleLanguageResolverInterface;

class TranslationsAssociationFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var WriteCommandExtractor
     */
    protected $writeExtrator;

    /**
     * @var LocaleLanguageResolverInterface
     */
    protected $localeLanguageResolver;

    public function __construct(
        WriteCommandExtractor $writeExtrator,
        LocaleLanguageResolverInterface $localeLanguageResolver
    ) {
        $this->writeExtrator = $writeExtrator;
        $this->localeLanguageResolver = $localeLanguageResolver;
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

            return $this->map($field, $data, $parameters);
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

            $languageId = $this->localeLanguageResolver->getLanguageByLocale($identifier, $parameters->getContext()->getContext());

            if (!isset($value[$languageId])) {
                $value[$languageId] = $fields;
            } else {
                $value[$languageId] = array_merge($value[$identifier], $value[$languageId]);
            }

            unset($value[$identifier]);
        }
        $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());

        return $this->map($field, $data, $parameters);
    }

    public function decode(Field $field, $value)
    {
        throw new DecodeByHydratorException($field);
    }

    protected function map(TranslationsAssociationField $field, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!\is_array($value)) {
            throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
        }

        $isNumeric = array_keys($value) === range(0, \count($value) - 1);

        foreach ($value as $keyValue => $subresources) {
            if (!\is_array($subresources)) {
                throw new MalformatDataException($parameters->getPath() . '/' . $key, 'Value must be an array.');
            }

            if ($field->getReferenceField() && !$isNumeric) {
                $subresources[$field->getReferenceField()] = $keyValue;
            }

            $this->writeExtrator->extract(
                $subresources,
                $parameters->cloneForSubresource(
                    $field->getReferenceClass(),
                    $parameters->getPath() . '/' . $key . '/' . $keyValue
                )
            );
        }

        return;
        yield __CLASS__ => __METHOD__;
    }
}
