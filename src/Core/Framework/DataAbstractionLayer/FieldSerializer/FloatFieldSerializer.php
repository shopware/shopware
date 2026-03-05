<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice as ChoiceFlag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Choice as ChoiceConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
class FloatFieldSerializer extends AbstractFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $choice = $field->getFlag(ChoiceFlag::class);
        if ($choice instanceof ChoiceFlag && $choice->isStrict() && \is_numeric($data->getValue())) {
            // Normalize numeric inputs (e.g. "1.5") to float before strict choice validation.
            $data->setValue((float) $data->getValue());
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if ($data->getValue() === null) {
            yield $field->getStorageName() => null;

            return;
        }

        yield $field->getStorageName() => (float) $data->getValue();
    }

    public function decode(Field $field, mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new NotBlank(),
            new Type('numeric'),
        ];

        $choice = $field->getFlag(ChoiceFlag::class);
        if ($choice instanceof ChoiceFlag && $choice->isStrict() && $choice->getChoices() !== []) {
            $constraints[] = new ChoiceConstraint(
                choices: array_map(static fn (string|bool|int|float $value): float => (float) $value, $choice->getChoices()),
                strict: true
            );
        }

        return $constraints;
    }
}
