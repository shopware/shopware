<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\NumberRangeValueField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator\ValueGeneratorHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class NumberRangeValueFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var ValueGeneratorHandlerInterface
     */
    private $valueGeneratorHandler;

    public function __construct(ValueGeneratorHandlerInterface $valueGeneratorHandler)
    {
        $this->valueGeneratorHandler = $valueGeneratorHandler;
    }

    public function getFieldClass(): string
    {
        return NumberRangeValueField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof NumberRangeValueField) {
            throw new InvalidSerializerFieldException(IdField::class, $field);
        }
        $value = $data->getValue();
        if (!$value) {
            $this->valueGeneratorHandler->setContext($parameters->getContext()->getContext());
            $value = $this->valueGeneratorHandler->getValue();
        }

        $parameters->getContext()->set($parameters->getDefinition(), $data->getKey(), $value);

        /* @var IdField $field */
        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value)
    {
        return $value;
    }
}
