<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class TimeRangeRule extends Rule
{
    private const TIME_REGEX = '/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';

    /**
     * @var string '15:59' as an example
     */
    protected $fromTime;

    /**
     * @var string '15:59' as an example
     */
    protected $toTime;

    public function getName(): string
    {
        return 'timeRange';
    }

    public function match(RuleScope $scope): Match
    {
        $from = $this->extractTime($this->fromTime);
        $to = $this->extractTime($this->toTime);
        $now = new \DateTime();

        if ($now < $from && $to <= $from) {
            $from->modify('-1 day');
        } elseif ($now >= $from && $to <= $from) {
            $to->modify('+1 day');
        }

        return new Match($to > $now && $from < $now, ['not in the given time range']);
    }

    public function getConstraints(): array
    {
        return [
            'toTime' => [new NotBlank(), new Regex(self::TIME_REGEX)],
            'fromTime' => [new NotBlank(), new Regex(self::TIME_REGEX)],
        ];
    }

    private function extractTime(string $time): \DateTime
    {
        [$hour, $minute] = explode(':', $time);

        return (new \DateTime())->setTime((int) $hour, (int) $minute);
    }
}
