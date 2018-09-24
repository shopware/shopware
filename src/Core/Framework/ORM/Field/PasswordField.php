<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class PasswordField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var int
     */
    private $algorithm;

    /**
     * @var array
     */
    private $hashOptions;

    public function __construct(string $storageName, string $propertyName, int $algorithm = PASSWORD_BCRYPT, array $hashOptions = [])
    {
        parent::__construct($propertyName);
        $this->storageName = $storageName;
        $this->algorithm = $algorithm;
        $this->hashOptions = $hashOptions;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
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

        if ($value) {
            $value = password_hash($value, $this->algorithm, $this->hashOptions);
        }

        yield $this->storageName => $value;
    }

    /**
     * @param array  $constraints
     * @param string $fieldName
     * @param mixed  $value
     */
    private function validate(array $constraints, string $fieldName, $value)
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

        if (\count($violationList)) {
            throw new InvalidFieldException($this->path . '/' . $fieldName, $violationList);
        }
    }

    /**
     * @return array
     */
    private function getInsertConstraints(): array
    {
        if ($this->is(Required::class)) {
            $this->constraintBuilder->isNotBlank();
        }

        return $this->constraintBuilder
            ->isString()
            ->getConstraints();
    }

    /**
     * @return array
     */
    private function getUpdateConstraints(): array
    {
        if ($this->is(Required::class)) {
            $this->constraintBuilder->isNotBlank();
        }

        return $this->constraintBuilder
            ->isString()
            ->getConstraints();
    }
}
