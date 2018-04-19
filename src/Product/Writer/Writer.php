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

namespace Shopware\Product\Writer;

use Shopware\Product\Writer\Api\DefaultUpdateField;
use Shopware\Product\Writer\Api\Field;
use Shopware\Product\Writer\Api\FieldCollection;
use Shopware\Product\Writer\Api\VirtualField;
use Shopware\Product\Writer\Api\WritableField;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Writer
{
    /**
     * @var SqlGateway
     */
    private $gateway;

    /**
     * @var FieldCollection
     */
    private $fieldCollection;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param SqlGateway      $gateway
     * @param FieldCollection $fieldCollection
     */
    public function __construct(
        SqlGateway $gateway,
        FieldCollection $fieldCollection,
        ValidatorInterface $validator
    ) {
        $this->gateway = $gateway;
        $this->fieldCollection = $fieldCollection;
        $this->validator = $validator;
    }

    public function insert(array $rawData): void
    {
        $data = $this->filterInputKeys($rawData);

        $this->gateway->insert($data);
    }

    /**
     * not here
     * 1. deserialize - HTTP Responsibility
     *
     * @param string $uuid
     * @param array  $rawData
     */
    public function update(string $uuid, array $rawData): void
    {
        $writableFields = $this->fieldCollection->getFields(WritableField::class);
        $virtualFields = $this->fieldCollection->getFields(VirtualField::class);
        $defaultFields = $this->fieldCollection->getFields(DefaultUpdateField::class);

        // 2. Normalize Collection

        // 2.1 Extract ids from subresources in collection - worky but dirty
        /** @var VirtualField $virtualField */
        foreach ($virtualFields as $virtualField) {
            if (!array_key_exists($virtualField->getName(), $rawData)) {
                continue;
            }

            $field = $this->fieldCollection
                ->getField($virtualField->getReferencedFieldClass());

            $rawData[$field->getName()] = $rawData[$virtualField->getName()]['uuid'];
        }

        $data = [];
        foreach ($writableFields as $field) {
            $name = $field->getName();

            // 2.2 filter unknown columns field based -- OK
            if (!array_key_exists($name, $rawData)) {
                continue;
            }

            $rawValue = $rawData[$name];

            // 3. escaping / filtering - e.g. remove html input, map to password, etc pp --- OK
            $rawValue = $this->applyFilters($field->getFilters(), $rawValue);

            // 4. validation
            $violations = $this->applyValidation($field->getUpdateConstraints(), $field->getName(), $rawValue);

            if (count($violations)) {
                throw new \InvalidArgumentException(sprintf('The value for %s is invalid', $field->getName()));
            }

            // 5. to database value -- OK
            $data[$field->getStorageName()] = $field->getValueTransformer()->transform($rawValue);
        }

        // 5.1 Add default columns - eg. updated_at
        /** @var DefaultUpdateField $defaultField */
        foreach ($defaultFields as $defaultField) {
            if (array_key_exists($defaultField->getStorageName(), $data)) {
                continue;
            }

            $data[$defaultField->getStorageName()] = $defaultField->getValue();
        }

        // 6. write
        $this->gateway->update($uuid, $data);
    }

    protected function applyValidation(array $constraints, string $fieldName, $value)
    {
        $violationList = new ConstraintViolationList();

        foreach ($constraints as $constraint) {
            $violations = $this->validator
                ->validate($value, $constraint);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $violationList->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $fieldName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause()
                    )
                );
            }
        }

        return $violationList;
    }

    /**
     * @param array $filters
     * @param $value
     *
     * @return mixed
     */
    protected function applyFilters(array $filters, $value)
    {
        foreach ($filters as $filter) {
            $value = $filter->filter($value);
        }

        return $value;
    }

    /**
     * @param array $rawData
     *
     * @return array
     */
    protected function filterInputKeys(array $rawData): array
    {
        $fieldNames = $this->fieldCollection->getFieldNames(WritableField::class);

        $data = array_filter($rawData, function (string $key) use ($fieldNames) {
            return false !== in_array($key, $fieldNames, true);
        }, ARRAY_FILTER_USE_KEY);

        return $data;
    }
}
