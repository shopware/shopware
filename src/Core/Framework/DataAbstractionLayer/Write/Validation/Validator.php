<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Package('core')]
class Validator
{
    private array $data = [];

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @param Constraint[] $constraints
     */
    public function addConstraint(string $propertyName, $propertyValue, array $constraints): void
    {
        $this->data[] = [
            'name' => $propertyName,
            'value' => $propertyValue,
            'constraints' => $constraints,
        ];
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        $violationList = new ConstraintViolationList();

        /*
         * @var mixed
         * @var Constraint $constraint
         */
        foreach ($this->data as $assertion) {
            $fieldName = $assertion['name'];
            $value = $assertion['value'];

            /** @var Constraint $constraint */
            foreach ($assertion['constraints'] as $constraint) {
                $violations = $this->validator->validate($value, $constraint);

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
        }

        return $violationList;
    }
}
