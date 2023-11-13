<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

#[Package('core')]
class ConstraintBuilder
{
    private array $constraints = [];

    /**
     * Set prop must not be blank (required)
     */
    public function isNotBlank(): self
    {
        $this->addConstraint(new NotBlank());

        return $this;
    }

    public function isNotNull(): self
    {
        $this->addConstraint(new NotNull());

        return $this;
    }

    public function isBool(): self
    {
        $this->addConstraint(new Type('bool'));

        return $this;
    }

    public function isString(): self
    {
        $this->addConstraint(new Type('string'));

        return $this;
    }

    public function isNumeric(): self
    {
        $this->addConstraint(new Type('numeric'));

        return $this;
    }

    public function isFloat(): self
    {
        $this->addConstraint(new Type('numeric'));

        return $this;
    }

    public function isLengthLessThanOrEqual(int $maxLength): self
    {
        $this->addConstraint(new Length(['max' => $maxLength]));

        return $this;
    }

    public function isGreaterThanOrEqual(int $value): self
    {
        $this->addConstraint(new GreaterThanOrEqual($value));

        return $this;
    }

    public function isLessThanOrEqual(int $value): self
    {
        $this->addConstraint(new LessThanOrEqual($value));

        return $this;
    }

    public function isDate(): self
    {
        $this->addConstraint(new Type(\DateTimeInterface::class));

        return $this;
    }

    public function isInt(): self
    {
        $this->addConstraint(new Type('int'));

        return $this;
    }

    /**
     * Set prop must be email
     */
    public function isEmail(): self
    {
        $this->addConstraint(new Email(['mode' => 'strict']));

        return $this;
    }

    /**
     * Set prop must be blank
     */
    public function isBlank(): self
    {
        $this->addConstraint(new Blank());

        return $this;
    }

    /**
     * Set prop must be in array
     */
    public function isInArray(array $values): self
    {
        $this->addConstraint(new Choice($values));

        return $this;
    }

    /**
     * Set prop must be an array
     */
    public function isArray(): self
    {
        $this->addConstraint(new Type('array'));

        return $this;
    }

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
