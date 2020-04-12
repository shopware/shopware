<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\Field\GoogleAccountCredentialField;
use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\GoogleAccountCredential;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class GoogleAccountCredentialFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof GoogleAccountCredentialField) {
            throw new InvalidSerializerFieldException(GoogleAccountCredentialField::class, $field);
        }

        $value = $data->getValue();

        if (is_object($value)) {
            $value = $value->normalize();
        }

        $data->setValue($value);

        if ($field->is(Required::class)) {
            $this->validate([new NotBlank()], $data, $parameters->getPath());
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => parent::encodeJson($value);
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        $credentials = json_decode($value, true);

        return new GoogleAccountCredential($credentials);
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Collection([
                'allowExtraFields' => false,
                'allowMissingFields' => false,
                'fields' => [
                    'access_token' => [new Required()],
                    'refresh_token' => [new Required()],
                    'id_token' => [new Required()],
                    'scope' => [new Optional()],
                    'created' => [new Required(), new Type('numeric')],
                    'expires_in' => [new Required(), new Type('numeric')],
                ],
            ]),
        ];
    }
}
