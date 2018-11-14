<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ObjectFieldSerializer extends JsonFieldSerializer
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        FieldSerializerRegistry $compositeHandler,
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ) {
        parent::__construct($compositeHandler, $constraintBuilder, $validator);
        $this->serializer = $serializer;
    }

    public function getFieldClass(): string
    {
        return ObjectField::class;
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        return $this->serializer->deserialize($value, '', 'json');
    }

    protected function getConstraints(WriteParameterBag $parameters): array
    {
        $constraints = [];

        $constraints[] = new Callback([
            'callback' => function ($object, ExecutionContextInterface $context) use ($parameters) {
                if (is_array($object) && array_key_exists('_class', $object)) {
                    $object = $this->serializer->deserialize(json_encode($object), '', 'json');
                }

                if ($object === null || $object instanceof Struct) {
                    return;
                }

                $context->buildViolation('The object must be of type "\Shopware\Core\Framework\Struct\Struct" to be persisted in a ObjectField.')
                    ->atPath($parameters->getPath())
                    ->addViolation()
                ;
            },
        ]);

        return $constraints;
    }
}
