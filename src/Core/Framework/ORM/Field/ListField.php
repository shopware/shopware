<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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

    /**
     * {@inheritdoc}
     */
    public function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
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

        if (count($exceptions)) {
            throw new InvalidJsonFieldException($this->path . '/' . $this->getPropertyName(), $exceptions);
        }
    }
}
