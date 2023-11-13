<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

#[Package('services-settings')]
class DateRangeRule extends Rule
{
    final public const RULE_NAME = 'dateRange';

    /**
     * @var \DateTimeInterface|string|null
     */
    protected $fromDate;

    /**
     * @var \DateTimeInterface|string|null
     */
    protected $toDate;

    /**
     * @var bool
     */
    protected $useTime;

    /**
     * @internal
     */
    public function __construct(
        ?\DateTimeInterface $fromDate = null,
        ?\DateTimeInterface $toDate = null,
        bool $useTime = false
    ) {
        parent::__construct();
        $this->useTime = $useTime;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    public function __wakeup(): void
    {
        if (\is_string($this->fromDate)) {
            $this->fromDate = new \DateTime($this->fromDate);
        }
        if (\is_string($this->toDate)) {
            $this->toDate = new \DateTime($this->toDate);
        }
    }

    public function match(RuleScope $scope): bool
    {
        if (\is_string($this->toDate) || \is_string($this->fromDate)) {
            throw new \LogicException('fromDate or toDate cannot be a string at this point.');
        }
        $toDate = $this->toDate;
        $fromDate = $this->fromDate;
        $now = $scope->getCurrentTime();

        if (!$this->useTime && $fromDate) {
            $fromDate = (new \DateTime())
                ->setTimestamp($fromDate->getTimestamp())
                ->setTime(0, 0);
        }

        if (!$this->useTime && $toDate) {
            $toDate = (new \DateTime())
                ->setTimestamp($toDate->getTimestamp())
                ->add(new \DateInterval('P1D'))
                ->setTime(0, 0);
        }

        if ($fromDate && $fromDate > $now) {
            return false;
        }

        if ($toDate && $toDate <= $now) {
            return false;
        }

        return true;
    }

    public function getConstraints(): array
    {
        return [
            'fromDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'toDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'useTime' => [new NotNull(), new Type('bool')],
        ];
    }
}
