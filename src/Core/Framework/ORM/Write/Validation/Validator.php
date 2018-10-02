<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Validator
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param string       $propertyName
     * @param mixed        $propertyValue
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

    /**
     * @return ConstraintViolationListInterface
     */
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
            $constraints = $assertion['constraints'];

            /**
             * @var Constraint[]
             * @var Constraint   $constraint
             */
            foreach ($constraints as $constraint) {
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
