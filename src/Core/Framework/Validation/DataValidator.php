<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('core')]
class DataValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function getViolations(array $data, DataValidationDefinition $definition, string $path = ''): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        $violations->addAll($this->validateProperties($data, $definition, $path));
        $violations->addAll($this->validateSubDefinitions($data, $definition, $path));
        $violations->addAll($this->validateListDefinitions($data, $definition, $path));

        return $violations;
    }

    public function validate(array $data, DataValidationDefinition $definition, string $path = ''): void
    {
        $violations = $this->getViolations($data, $definition, $path);
        if ($violations->count() === 0) {
            return;
        }

        throw new ConstraintViolationException($violations, $data);
    }

    private function validateProperties(array $data, DataValidationDefinition $definition, string $path): ConstraintViolationList
    {
        $constraintViolations = new ConstraintViolationList();

        foreach ($definition->getProperties() as $propertyName => $constraints) {
            $value = $data[$propertyName] ?? null;
            $violations = $this->validator->validate($value, $constraints);

            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $constraintViolations->add(
                    new ConstraintViolation(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getRoot(),
                        $path . '/' . $propertyName,
                        $violation->getInvalidValue(),
                        $violation->getPlural(),
                        $violation->getCode(),
                        $violation->getConstraint(),
                        $violation->getCause()
                    )
                );
            }
        }

        return $constraintViolations;
    }

    private function validateSubDefinitions(array $data, DataValidationDefinition $definition, string $path): ConstraintViolationList
    {
        $constraintViolations = new ConstraintViolationList();

        foreach ($definition->getSubDefinitions() as $propertyName => $subDefinition) {
            $value = $data[$propertyName] ?? [];
            $constraintViolations->addAll(
                $this->getViolations($value, $subDefinition, $path . '/' . $propertyName)
            );
        }

        return $constraintViolations;
    }

    private function validateListDefinitions(array $data, DataValidationDefinition $definition, string $path): ConstraintViolationList
    {
        $constraintViolations = new ConstraintViolationList();

        foreach ($definition->getListDefinitions() as $propertyName => $subDefinition) {
            $values = $data[$propertyName] ?? [];

            $i = 0;
            foreach ($values as $item) {
                $constraintViolations->addAll(
                    $this->getViolations($item, $subDefinition, $path . '/' . $propertyName . '/' . $i)
                );
                ++$i;
            }
        }

        return $constraintViolations;
    }
}
