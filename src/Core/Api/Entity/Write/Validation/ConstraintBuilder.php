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

namespace Shopware\Api\Entity\Write\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class ConstraintBuilder
{
    /**
     * @var array
     */
    private $constraints = [];

    /**
     * Set prop must not be blank (required)
     *
     * @return $this
     */
    public function isNotBlank()
    {
        $this->addConstraint(new NotBlank());

        return $this;
    }

    /**
     * @return $this
     */
    public function isBool()
    {
        $this->addConstraint(new Type('bool'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isString()
    {
        $this->addConstraint(new Type('string'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isNumeric()
    {
        $this->addConstraint(new Type('numeric'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isFloat()
    {
        $this->addConstraint(new Type('float'));

        return $this;
    }

    /**
     * @param int $maxLength
     *
     * @return $this
     */
    public function isShorterThen(int $maxLength)
    {
        $this->addConstraint(new Length(['max' => $maxLength]));

        return $this;
    }

    /**
     * @return $this
     */
    public function isDate()
    {
        $this->addConstraint(new Type(\DateTimeInterface::class));

        return $this;
    }

    /**
     * @return $this
     */
    public function isInt()
    {
        $this->addConstraint(new Type('int'));

        return $this;
    }

    /**
     * Set prop must be email
     *
     * @return $this
     */
    public function isEmail()
    {
        $this->addConstraint(new Email());

        return $this;
    }

    /**
     * Set prop must be blank
     *
     * @return $this
     */
    public function isBlank()
    {
        $this->addConstraint(new Blank());

        return $this;
    }

    /**
     * Set prop must be in array
     *
     * @param array $values
     *
     * @return $this
     */
    public function isInArray(array $values)
    {
        $this->addConstraint(new Choice($values));

        return $this;
    }

    /**
     * @param Constraint $constraint
     *
     * @return ConstraintBuilder
     */
    public function addConstraint(Constraint $constraint): self
    {
        $this->constraints[] = $constraint;

        return $this;
    }

    /**
     * @return Constraint[]
     */
    public function getConstraints(): array
    {
        $ret = $this->constraints;

        $this->constraints = [];

        return $ret;
    }
}
