<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Field;

use Shopware\Framework\Api2\ApiFilter\FilterRegistry;
use Shopware\Framework\Api2\ApiFilter\HtmlFilter;
use Shopware\Framework\Api2\FieldAware\ConstraintBuilderAware;
use Shopware\Framework\Api2\FieldAware\FilterRegistryAware;
use Shopware\Framework\Api2\FieldAware\PathAware;
use Shopware\Framework\Api2\FieldAware\ValidatorAware;
use Shopware\Framework\Api2\FieldException\InvalidFieldException;
use Shopware\Framework\Api2\Resource\ApiResource;
use Shopware\Framework\Validation\ConstraintBuilder;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class IntField extends Field implements PathAware, ConstraintBuilderAware, FilterRegistryAware, ValidatorAware
{
    /**
     * @var ConstraintBuilder
     */
    private $constraintBuilder;

    /**
     * @var FilterRegistry
     */
    private $filterRegistry;

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

    public function setFilterRegistry(FilterRegistry $filterRegistry): void
    {
        $this->filterRegistry = $filterRegistry;
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

        yield $this->storageName => $value;
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
            ->isInt()
            ->isShorterThen(255)
            ->getConstraints();

    }

    private function getUpdateConstraints(): array
    {
        return $this->constraintBuilder
            ->isInt()
            ->isShorterThen(255)
            ->getConstraints();

    }

    private function getFilter()
    {
        return $this->filterRegistry
            ->get(HtmlFilter::class);
    }
}