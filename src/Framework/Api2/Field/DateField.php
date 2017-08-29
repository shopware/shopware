<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\ApiValueTransformer\ValueTransformer;
use Shopware\Framework\Api2\ApiValueTransformer\ValueTransformerDate;
use Shopware\Framework\Api2\ApiValueTransformer\ValueTransformerRegistry;
use Shopware\Framework\Api2\FieldAware\ConstraintBuilderAware;
use Shopware\Framework\Api2\FieldAware\PathAware;
use Shopware\Framework\Api2\FieldAware\ValidatorAware;
use Shopware\Framework\Api2\FieldAware\ValueTransformerRegistryAware;
use Shopware\Framework\Api2\FieldException\InvalidFieldException;
use Shopware\Framework\Api2\Resource\ApiResource;
use Shopware\Framework\Validation\ConstraintBuilder;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DateField extends Field implements PathAware, ConstraintBuilderAware, ValueTransformerRegistryAware, ValidatorAware
{
    /**
     * @var ConstraintBuilder
     */
    private $constraintBuilder;

    /**
     * @var ValidatorInterface
     */
    private $validator;
    /**
     * @var string
     */
    private $storageName;

    /**
     * @var string
     */
    private $path;

    /**
     * @var ValueTransformerRegistry
     */
    private $valueTransformerRegistry;

    /**
     * @param string $storageName
     */
    public function __construct(string $storageName)
    {
        $this->storageName = $storageName;
    }

    public function setConstraintBuilder(ConstraintBuilder $constraintBuilder): void
    {
        $this->constraintBuilder = $constraintBuilder;
    }

    public function setValueTransformerRegistry(ValueTransformerRegistry $valueTransformerRegistry): void
    {
        $this->valueTransformerRegistry = $valueTransformerRegistry;
    }

    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function setPath(string $path = ''): void
    {
        $this->path = $path;
    }

    public function __invoke(string $type, string $key, $value = null): \Generator
    {
        switch ($type) {
            case ApiResource::FOR_INSERT:
                $this->validate($this->getInsertConstraints(), $key, $value);
                break;
            case ApiResource::FOR_UPDATE:
                $this->validate($this->getUpdateConstraints(), $key, $value);
                break;
            default:
                throw new \DomainException(sprintf('Could not understand %s', $type));
        }

        yield $this->storageName => $this->getValueTransformer()->transform($value);
    }

    /**
     * @param array $constraints
     * @param string $fieldName
     * @param $value
     */
    private function validate(array $constraints, string $fieldName, $value)
    {
        $violationList = new ConstraintViolationList();

        foreach($constraints as $constraint) {
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

    private function getInsertConstraints(): array
    {
        return $this->constraintBuilder
            ->isNotBlank()
            ->isDate()
            ->getConstraints();

    }

    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isDate()
            ->getConstraints();

    }

    private function getValueTransformer(): ValueTransformer
    {
        return $this->valueTransformerRegistry
            ->get(ValueTransformerDate::class);
    }
}