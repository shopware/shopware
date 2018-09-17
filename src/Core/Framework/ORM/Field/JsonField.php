<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\DataStack;
use Shopware\Core\Framework\ORM\Write\DataStack\ExceptionNoStackItemFound;
use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidJsonFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class JsonField extends Field implements StorageAware
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

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return Field[]
     */
    public function getPropertyMapping(): array
    {
        return $this->propertyMapping;
    }

    /**
     * {@inheritdoc}
     */
    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        $key = $data->getKey();
        $value = $data->getValue();

        if (!empty($this->propertyMapping) && $value) {
            $value = $this->validateMapping($value);
        }

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        } else {
            $this->validate($this->getInsertConstraints(), $key, $value);
        }

        if ($value !== null) {
            $value = json_encode($value);
        }

        yield $this->storageName => $value;
    }

    /**
     * @param array  $constraints
     * @param string $fieldName
     * @param mixed  $value
     */
    protected function validate(array $constraints, string $fieldName, $value): void
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

    /**
     * @return Constraint[]
     */
    protected function getInsertConstraints(): array
    {
        if ($this->is(Required::class)) {
            $this->constraintBuilder->addConstraint(new NotBlank());
        }

        return $this->constraintBuilder
            ->getConstraints();
    }

    /**
     * @return Constraint[]
     */
    protected function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->getConstraints();
    }

    private function validateMapping(array $data): array
    {
        if (array_key_exists('_class', $data)) {
            unset($data['_class']);
        }

        $exceptions = [];
        $stack = new DataStack($data);
        $existence = new EntityExistence('', [], false, false, false, []);
        $fieldPath = $this->path . '/' . $this->getPropertyName();

        $propertyKeys = array_map(function (Field $field) {
            return $field->getPropertyName();
        }, $this->propertyMapping);

        // If a mapping is defined, you should not send properties that are undefined.
        // Sending undefined fields will throw an UnexpectedFieldException
        $keyDiff = array_diff(array_keys($data), $propertyKeys);
        if (count($keyDiff)) {
            foreach ($keyDiff as $fieldName) {
                $exceptions[] = new UnexpectedFieldException($fieldPath . '/' . $fieldName, $fieldName);
            }
        }

        foreach ($this->propertyMapping as $field) {
            try {
                $kvPair = $stack->pop($field->getPropertyName());
            } catch (ExceptionNoStackItemFound $e) {
                // The writer updates the whole field, so there is no possibility to update
                // "some" fields. To enable a merge, we have to respect the $existence state
                // for correct constraint validation. In addition the writer has to be rewritten
                // in order to handle merges.
                if (!$field->is(Required::class)) {
                    continue;
                }

                $kvPair = new KeyValuePair($field->getPropertyName(), null, true);
            }

            $this->fieldExtenderCollection->extend($field);
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
