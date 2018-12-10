<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

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
    public function isNotBlank(): self
    {
        $this->addConstraint(new NotBlank());

        return $this;
    }

    /**
     * @return $this
     */
    public function isBool(): self
    {
        $this->addConstraint(new Type('bool'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isString(): self
    {
        $this->addConstraint(new Type('string'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isNumeric(): self
    {
        $this->addConstraint(new Type('numeric'));

        return $this;
    }

    /**
     * @return $this
     */
    public function isFloat(): self
    {
        $this->addConstraint(new Type('numeric'));

        return $this;
    }

    /**
     * @param int $maxLength
     *
     * @return $this
     */
    public function isLengthLessThanOrEqual(int $maxLength): self
    {
        $this->addConstraint(new Length(['max' => $maxLength]));

        return $this;
    }

    /**
     * @return $this
     */
    public function isDate(): self
    {
        $this->addConstraint(new Type(\DateTimeInterface::class));

        return $this;
    }

    /**
     * @return $this
     */
    public function isInt(): self
    {
        $this->addConstraint(new Type('int'));

        return $this;
    }

    /**
     * Set prop must be email
     *
     * @return $this
     */
    public function isEmail(): self
    {
        $this->addConstraint(new Email());

        return $this;
    }

    /**
     * Set prop must be blank
     *
     * @return $this
     */
    public function isBlank(): self
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
    public function isInArray(array $values): self
    {
        $this->addConstraint(new Choice($values));

        return $this;
    }

    /**
     * Set prop must be an array
     *
     * @return $this
     */
    public function isArray(): self
    {
        $this->addConstraint(new Type('array'));

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
