<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class DateRangeRule extends Rule
{
    final public const RULE_NAME = 'dateRange';

    private const DATETIME_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * @internal
     */
    public function __construct(
        protected \DateTimeInterface|string|null $fromDate = null,
        protected \DateTimeInterface|string|null $toDate = null,
        protected bool $useTime = false,
        protected \DateTimeZone|string|null $timezone = null,
    ) {
        parent::__construct();
    }

    public function __wakeup(): void
    {
        if (\is_string($this->fromDate)) {
            $this->fromDate = new \DateTime($this->fromDate);
        }
        if (\is_string($this->toDate)) {
            $this->toDate = new \DateTime($this->toDate);
        }
        if (\is_string($this->timezone)) {
            $this->timezone = new \DateTimeZone($this->timezone);
        }
    }

    public function match(RuleScope $scope): bool
    {
        if (\is_string($this->toDate) || \is_string($this->fromDate) || \is_string($this->timezone)) {
            throw RuleException::invalidDateRangeUsage('fromDate, toDate and timezone cannot be a string at this point');
        }
        $toDate = $this->toDate;
        $fromDate = $this->fromDate;
        $timezone = $this->timezone;
        $now = $scope->getCurrentTime();

        if ($timezone) {
            if ($fromDate) {
                $fromDate = new \DateTime($fromDate->format('Y-m-d H:i:s'), $timezone);
            }
            if ($toDate) {
                $toDate = new \DateTime($toDate->format('Y-m-d H:i:s'), $timezone);
            }
        }

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
            'fromDate' => [new NotBlank(), new DateTimeConstraint(format: self::DATETIME_FORMAT)],
            'toDate' => [new NotBlank(), new DateTimeConstraint(format: self::DATETIME_FORMAT)],
            'useTime' => [new NotNull(), new Type('bool')],
            'timezone' => [new Timezone()],
        ];
    }
}
