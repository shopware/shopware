<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class DateRangeRule extends Rule
{
    /**
     * @var \DateTimeInterface|null
     */
    protected $fromDate;

    /**
     * @var \DateTimeInterface|null
     */
    protected $toDate;

    /**
     * @var bool
     */
    protected $useTime;

    public function __construct()
    {
        $this->useTime = false;
    }

    public function match(RuleScope $scope): bool
    {
        $now = new \DateTime();

        if (!$this->useTime) {
            $now->setTime(0, 0);
        }

        if ($this->fromDate && $this->fromDate >= $now) {
            return false;
        }

        if ($this->toDate && $this->toDate < $now) {
            return false;
        }

        return true;
    }

    public function getConstraints(): array
    {
        return [
            'fromDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'toDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'useTime' => [new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'dateRange';
    }
}
