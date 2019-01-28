<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Attribute\AttributeEntity;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttributesFieldSerializer extends JsonFieldSerializer
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    public function __construct(FieldSerializerRegistry $compositeHandler, ConstraintBuilder $constraintBuilder, ValidatorInterface $validator, EntityRepositoryInterface $attributeRepository)
    {
        parent::__construct($compositeHandler, $constraintBuilder, $validator);
        $this->attributeRepository = $attributeRepository;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof AttributesField) {
            throw new InvalidSerializerFieldException(AttributesField::class, $field);
        }

        $attributes = $data->getValue();
        foreach ($attributes as $attribute => $value) {
            $attributes[$attribute] = $this->encodeAttribute($attribute, $value, $parameters->getContext()->getContext());
        }

        // entity does not exist: simply encode
        if (!$existence->exists()) {
            yield from parent::encode($field, $existence, new KeyValuePair($data->getKey(), $attributes, $data->isRaw()), $parameters);

            return;
        }

        $jsonUpdateCommand = new JsonUpdateCommand(
            $existence->getDefinition(),
            $field->getStorageName(),
            $existence->getPrimaryKey(),
            $attributes,
            $existence
        );
        $parameters->getCommandQueue()->add($jsonUpdateCommand->getDefinition(), $jsonUpdateCommand);

        return;
        yield __CLASS__ => __METHOD__;
    }

    public function decode(Field $field, $attributes)
    {
        if (!is_array($attributes)) {
            $attributes = parent::decode($field, $attributes);
        }

        foreach ($attributes as $attribute => $value) {
            // TODO: dont use the default context...
            $attributes[$attribute] = $this->decodeAttribute($attribute, $value, Context::createDefaultContext());
        }

        return $attributes;
    }

    public function getFieldClass(): string
    {
        return AttributesField::class;
    }

    private function getAttribute(string $attribute, Context $context): ?AttributeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $attribute));

        return $this->attributeRepository
            ->search($criteria, $context)
            ->first();
    }

    private function encodeAttribute(string $attribute, $value, Context $context)
    {
        $attribute = $this->getAttribute($attribute, $context);
        if (!$attribute) {
            return $value;
        }
        if ($value === null) {
            return null;
        }

        switch ($attribute->getType()) {
            case AttributeTypes::BOOL:
                return (bool) $value;
            case AttributeTypes::DATETIME:
                if ($value instanceof \DateTime) {
                    return $value->format(Defaults::DATE_FORMAT);
                } elseif (is_string($value)) {
                    return (new \DateTime($value))->format(Defaults::DATE_FORMAT);
                }

                return null;
            case AttributeTypes::INT:
                return (int) $value;
            case AttributeTypes::FLOAT:
                return (float) $value;
            default:
                return $value;
        }
    }

    private function decodeAttribute(string $attribute, $value, Context $context)
    {
        if ($value === null) {
            return null;
        }

        $attribute = $this->getAttribute($attribute, $context);
        if (!$attribute) {
            return $value;
        }

        switch ($attribute->getType()) {
            case AttributeTypes::BOOL:
                return (bool) $value;
            case AttributeTypes::DATETIME:
                if (!is_string($value)) {
                    return null;
                }
                try {
                    return new \DateTime($value);
                } catch (\Exception $e) {
                    return null;
                }
            case AttributeTypes::INT:
                return (int) $value;
            case AttributeTypes::FLOAT:
                return (float) $value;
            default:
                return $value;
        }
    }
}
