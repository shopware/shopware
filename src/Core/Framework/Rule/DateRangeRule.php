<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use DateTime;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

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

    public function __construct()
    {
        $this->useTime = false;
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

    public static function getConstraints(): array
    {
        return [
            'fromDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'toDate' => [new NotBlank(), new DateTimeConstraint(['format' => \DateTime::ATOM])],
            'useTime' => [new Type('bool')],
        ];
    }

    public static function getName(): string
    {
        return 'date_range';
    }
}
