<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LongTextFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof LongTextField) {
            throw new InvalidSerializerFieldException(LongTextField::class, $field);
        }

        if ($data->getValue() === '' && !$field->is(AllowEmptyString::class)) {
            $data->setValue(null);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if ($data->getValue() !== null && !$field->is(AllowHtml::class)) {
            $data->setValue(strip_tags((string) $data->getValue()));
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $data->getValue() !== null ? (string) $data->getValue() : null;
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): ?string
    {
        return $value;
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Type('string'),
        ];

        if (!$field->is(AllowEmptyString::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }
}
