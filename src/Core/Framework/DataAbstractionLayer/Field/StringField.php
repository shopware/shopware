<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldAware\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Filter\HtmlFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class StringField extends Field implements StorageAware
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(string $storageName, string $propertyName)
    {
        $this->storageName = $storageName;
        parent::__construct($propertyName);
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

        if ($value === null && !$this->is(Required::class)) {
            yield $this->storageName => $this->getFilter()->filter($value);

            return;
        }

        if ($existence->exists()) {
            $this->validate($this->getUpdateConstraints(), $key, $value);
        } else {
            $this->validate($this->getInsertConstraints(), $key, $value);
        }

        yield $this->storageName => $this->getFilter()->filter($value);
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
        return $this->constraintBuilder
            ->isNotBlank()
            ->isString()
            ->isShorterThen(255)
            ->getConstraints();
    }

    /**
     * @return array
     */
    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isString()
            ->isShorterThen(255)
            ->getConstraints();
    }

    /**
     * @return Filter
     */
    private function getFilter(): Filter
    {
        return $this->filterRegistry
            ->get(HtmlFilter::class);
    }
}
