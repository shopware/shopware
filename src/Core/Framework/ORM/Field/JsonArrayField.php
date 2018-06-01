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

use Shopware\Core\Framework\ORM\Write\DataStack\DataStack;
use Shopware\Core\Framework\ORM\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\FieldException\InvalidJsonFieldException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class JsonArrayField extends Field implements StorageAware
{
    /**
     * @var string
     */
    protected $storageName;

    /**
     * @var Field[]
     */
    protected $propertyMapping;

    public function __construct(string $storageName, string $propertyName, array $propertyMapping = [])
    {
        $this->storageName = $storageName;
        $this->propertyMapping = $propertyMapping;
        parent::__construct($propertyName);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!empty($this->propertyMapping) && is_array($value)) {
            $value = $this->validateMapping($value);
        }

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        } else {
            $this->validate($this->getInsertConstraints(), $key, $value);
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @param array  $constraints
     * @param string $fieldName
     * @param $value
     */
    public function validate(array $constraints, string $fieldName, $value): void
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

        if (count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }

    public function getInsertConstraints(): array
    {
        return $this->constraintBuilder
            ->isNotBlank()
            ->getConstraints();
    }

    public function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->getConstraints();
    }

    private function validateMapping(array $data): array
    {
        $exceptions = [];
        $stack = new DataStack($data);
        $existence = new EntityExistence('', [], false, false, false, []);

        foreach ($this->propertyMapping as $field) {
            try {
                $kvPair = $stack->pop($field->getPropertyName());
            } catch (ExceptionNoStackItemFound $e) {
                if (!$field->is(Required::class)) {
                    continue;
                }

                $kvPair = new KeyValuePair($field->getPropertyName(), null, true);
            }

            $field->setValidator($this->validator);
            $field->setConstraintBuilder($this->constraintBuilder);
            $field->setPath($this->path . '/' . $this->getPropertyName());

            try {
                foreach ($field($existence, $kvPair) as $fieldKey => $fieldValue) {
                    $stack->update($fieldKey, $fieldValue);
                }
            } catch (InvalidFieldException $exception) {
                $exceptions[] = $exception;
            } catch (InvalidJsonFieldException $exception) {
                $exceptions = array_merge($exceptions, $exception->getExceptions());
            }
        }

        if (count($exceptions)) {
            throw new InvalidJsonFieldException($this->path . '/' . $this->getPropertyName(), $exceptions);
        }

        return $stack->getResultAsArray();
    }
}
