<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\Type;

class DateFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof DateField) {
            throw new InvalidSerializerFieldException(DateField::class, $field);
        }

        $value = $data->getValue();

        if (\is_string($value)) {
            $value = new \DateTimeImmutable($value);
        }

        if (\is_array($value) && \array_key_exists('date', $value)) {
            $value = new \DateTimeImmutable($value['date']);
        }

        $data->setValue($value);
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        $value = $value->setTimezone(new \DateTimeZone('UTC'));

        yield $field->getStorageName() => $value->format(Defaults::STORAGE_DATE_FORMAT);
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): ?\DateTimeInterface
    {
        return $value === null ? null : new \DateTimeImmutable($value);
    }

    protected function getConstraints(Field $field): array
    {
        return [new Type(\DateTimeInterface::class)];
    }
}
