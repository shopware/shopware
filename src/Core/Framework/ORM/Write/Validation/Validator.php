<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
     * @param $propertyName
     * @param $propertyValue
     * @param Constraint[] $constraints
     */
    public function addConstraint($propertyName, $propertyValue, array $constraints)
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
