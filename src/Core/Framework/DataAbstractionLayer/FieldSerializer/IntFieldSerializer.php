<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice as ChoiceConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class IntFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly StrictChoiceResolver $strictChoiceResolver = new StrictChoiceResolver(),
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof IntField) {
            throw DataAbstractionLayerException::invalidSerializerField(IntField::class, $field);
        }

        $constraints = $this->getCachedConstraints($field);
        $choices = $this->strictChoiceResolver->getStrictChoices($field, $parameters->getDefinition()->getEntityName());
        if ($choices !== null) {
            $constraints[] = new ChoiceConstraint(choices: $choices, strict: true);
        }

        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $this->validate($constraints, $data, $parameters->getPath());
        }

        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * @param IntField $field
     *
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('int'),
            new NotBlank(),
        ];

        if ($field->getMinValue() !== null || $field->getMaxValue() !== null) {
            $constraints[] = new Range(min: $field->getMinValue(), max: $field->getMaxValue());
        }

        return $constraints;
    }
}
