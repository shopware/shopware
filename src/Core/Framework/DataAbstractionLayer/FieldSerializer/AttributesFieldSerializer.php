<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Attribute\AttributeServiceInterface;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttributesFieldSerializer extends JsonFieldSerializer
{
    /** @var WriteCommandExtractor */
    private $writeExtractor;

    /**
     * @var AttributeServiceInterface
     */
    private $attributeService;

    public function __construct(
        FieldSerializerRegistry $compositeHandler,
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator,
        AttributeServiceInterface $attributeService,
        WriteCommandExtractor $writeExtractor
    ) {
        parent::__construct($compositeHandler, $constraintBuilder, $validator);
        $this->attributeService = $attributeService;
        $this->writeExtractor = $writeExtractor;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof AttributesField) {
            throw new InvalidSerializerFieldException(AttributesField::class, $field);
        }

        // encode null and [] as null / delete attributes
        if (empty($data->getValue())) {
            yield $data->getKey() => null;

            return;
        }

        $encoded = [];
        foreach ($data->getValue() as $attributeName => $value) {
            $type = $this->attributeService->getAttributeType($attributeName);
            if (!$type) {
                continue;
            }
            $encoded[$attributeName] = $this->encodeAttribute($type, $value);
        }
        if (empty($encoded)) {
            return;
        }

        if ($existence->exists()) {
            $this->writeExtractor->extractJsonUpdate([$field->getStorageName() => $encoded], $existence, $parameters);

            return;
        }

        // entity does not exist: simply encode
        $kvPair = new KeyValuePair($data->getKey(), $encoded, $data->isRaw());
        yield from parent::encode($field, $existence, $kvPair, $parameters);
    }

    public function decode(Field $field, $attributes)
    {
        if ($attributes === null) {
            return null;
        }

        if (!is_array($attributes)) {
            $attributes = parent::decode($field, $attributes);
        }

        $decoded = [];
        foreach ($attributes as $attributeName => $value) {
            $type = $this->attributeService->getAttributeType($attributeName);
            if (!$type) {
                continue;
            }
            $decoded[$attributeName] = $this->decodeAttribute($type, $value);
        }

        return $decoded;
    }

    public function getFieldClass(): string
    {
        return AttributesField::class;
    }

    private function encodeAttribute(string $type, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case AttributeTypes::BOOL:
                return (bool) $value;
            case AttributeTypes::DATETIME:
                if ($value instanceof \DateTime) {
                    return $value->format(Defaults::DATE_FORMAT);
                }

                if (is_string($value)) {
                    return (new \DateTime($value))->format(Defaults::DATE_FORMAT);
                }

                return null;
            case AttributeTypes::INT:
                return (int) $value;
            case AttributeTypes::FLOAT:
                return (float) $value;
            case AttributeTypes::STRING:
            default:
                return $value;
        }
    }

    private function decodeAttribute(string $type, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case AttributeTypes::BOOL:
                return (bool) $value;
            case AttributeTypes::INT:
                return (int) $value;
            case AttributeTypes::FLOAT:
                return (float) $value;
            case AttributeTypes::DATETIME:
            case AttributeTypes::STRING:
            default:
                return $value;
        }
    }
}
