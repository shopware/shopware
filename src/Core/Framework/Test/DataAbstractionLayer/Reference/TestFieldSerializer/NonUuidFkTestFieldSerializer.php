<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestFieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Reference\TestField\NonUuidFkTestField;
use Symfony\Component\Validator\Constraints\NotBlank;

class NonUuidFkTestFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof NonUuidFkTestField) {
            throw new InvalidSerializerFieldException(NonUuidFkTestField::class, $field);
        }

        $value = $data->getValue();

        if ($this->shouldUseContext($field, $data)) {
            try {
                $value = $parameters->getContext()->get($field->getReferenceDefinition()->getClass(), $field->getReferenceField());
            } catch (\InvalidArgumentException $exception) {
                $this->validate($this->getConstraints($field), $data, $parameters->getPath());
            }
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value;
    }

    protected function shouldUseContext(FkField $field, KeyValuePair $data): bool
    {
        return $data->isRaw() && $data->getValue() === null && $field->is(Required::class);
    }

    protected function getConstraints(Field $field): array
    {
        return [new NotBlank()];
    }
}
