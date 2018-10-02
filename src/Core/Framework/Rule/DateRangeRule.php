<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use DateTime;

class DateRangeRule extends Rule
{
    /**
     * @var DateTime|null
     */
    protected $fromDate;

    /**
     * @var DateTime|null
     */
    protected $toDate;

    /**
     * @var bool
     */
    protected $useTime;

    /**
     * @param DateTime|null $fromDate
     * @param DateTime|null $toDate
     * @param bool          $useTime
     */
    public function __construct(?DateTime $fromDate, ?DateTime $toDate, $useTime = false)
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->useTime = $useTime;
    }

    public function match(RuleScope $scope): Match
    {
        $now = new DateTime();

        if (!$this->useTime) {
            $now->setTime(0, 0);
        }

        if ($this->fromDate && $this->fromDate >= $now) {
            return new Match(false, ['Not in date range']);
        }

        if ($this->toDate && $this->toDate < $now) {
            return new Match(false, ['Not in date range']);
        }

        return new Match(true);
    }
}
