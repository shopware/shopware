<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PasswordFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof PasswordField) {
            throw new InvalidSerializerFieldException(PasswordField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $value = $data->getValue();
        if ($value) {
            $info = password_get_info($value);
            // if no password algorithm is detected, it might be plain text which needs to be encoded.
            // otherwise, passthrough the possibly encoded string
            if (!$info['algo']) {
                $value = password_hash($value, $field->getAlgorithm(), $field->getHashOptions());
            }
        }

        yield $field->getStorageName() => $value;
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
        return [
            new NotBlank(),
            new Type('string'),
        ];
    }
}
