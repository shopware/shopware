<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidJsonFieldException;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Stores a JSON formatted value list. This can be typed using the third constructor parameter.
 *
 * Definition example:
 *
 *      // allow every type
 *      new ListField('product_ids', 'productIds');
 *
 *      // allow int types only
 *      new ListField('product_ids', 'productIds', IntField::class);
 *
 * Output in database:
 *
 *      // mixed type value
 *      ['this is a string', 'another string', true, 15]
 *
 *      // single type values
 *      [12,55,192,22]
 */
class ListField extends JsonField
{
    /**
     * @var null|string
     */
    private $fieldType;

    public function __construct(string $storageName, string $propertyName, string $fieldType = null)
    {
        parent::__construct($storageName, $propertyName);
        $this->fieldType = $fieldType;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function getInsertConstraints(): array
    {
        $constraints = parent::getInsertConstraints();
        $constraints[] = new Type('array');

        return $constraints;
    }

    public function getUpdateConstraints(): array
    {
        $constraints = parent::getInsertConstraints();
        $constraints[] = new Type('array');

        return $constraints;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        } else {
            $this->validate($this->getInsertConstraints(), $key, $value);
        }

        if ($value !== null) {
            $value = array_values($value);

            if ($this->getFieldType()) {
                $this->validateTypes($value);
            }

            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }

    private function validateTypes(array $values): void
    {
        $fieldType = $this->getFieldType();
        $exceptions = [];
        $existence = new EntityExistence('', [], false, false, false, []);

        /** @var Field $listField */
        $listField = new $fieldType('key', 'key');
        $this->fieldExtenderCollection->extend($listField);
        $listField->setPath($this->path . '/' . $this->getPropertyName());

        foreach ($values as $i => $value) {
            try {
                $kvPair = new KeyValuePair((string) $i, $value, true);
                iterator_to_array($listField($existence, $kvPair));
            } catch (InvalidFieldException $exception) {
                $exceptions[] = $exception;
            } catch (InvalidJsonFieldException $exception) {
                $exceptions = array_merge($exceptions, $exception->getExceptions());
            }
        }

        if (\count($exceptions)) {
            throw new InvalidJsonFieldException($this->path . '/' . $this->getPropertyName(), $exceptions);
        }
    }
}
