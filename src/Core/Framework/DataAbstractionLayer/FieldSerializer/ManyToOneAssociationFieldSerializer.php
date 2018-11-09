<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\MalformatDataException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ManyToOneAssociationFieldSerializer implements FieldSerializerInterface
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
        return ManyToOneAssociationField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ManyToOneAssociationField) {
            throw new \InvalidArgumentException(
                sprintf('Expected field of type %s got %s', ManyToOneAssociationField::class, \get_class($field))
            );
        }
        /* @var ManyToOneAssociationField $field */
        if (!\is_array($data->getValue())) {
            throw new MalformatDataException($parameters->getPath(), 'Expected array');
        }

        $this->writeExtractor->extract(
            $data->getValue(),
            $parameters->cloneForSubresource(
                $field->getReferenceClass(),
                $parameters->getPath() . '/' . $data->getKey()
            )
        );

        $id = $parameters->getContext()->get($field->getReferenceClass(), $field->getReferenceField());

        $fkField = $parameters->getDefinition()::getFields()->getByStorageName($field->getStorageName());

        /* @var FkField $fkField */
        yield $fkField->getPropertyName() => $id;
    }

    public function decode(Field $field, $value)
    {
        throw new DecodeByHydratorException($field);
    }
}
