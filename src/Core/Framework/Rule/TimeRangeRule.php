<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Timezone;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class TimeRangeRule extends Rule
{
    final public const RULE_NAME = 'timeRange';

    private const TIME_REGEX = '/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';

    protected string $fromTime;

    protected string $toTime;

    protected ?string $timezone = null;

    private bool $validationTurnover = false;

    private \DateTimeInterface $to;

    private \DateTimeInterface $from;

    public function match(RuleScope $scope): bool
    {
        $now = $scope->getCurrentTime();
        $this->from = $this->extractTime($this->fromTime, $this->timezone, $now);
        $this->to = $this->extractTime($this->toTime, $this->timezone, $now);

        $this->switchValidationIfToIsSmallerThanFrom();

        return $this->returnResultWithSightOnValidationTurnover($now);
    }

    public function getConstraints(): array
    {
        return [
            'toTime' => [new NotBlank(), new Regex(pattern: self::TIME_REGEX)],
            'fromTime' => [new NotBlank(), new Regex(pattern: self::TIME_REGEX)],
            'timezone' => [new Timezone()],
        ];
    }

    private function extractTime(string $time, ?string $timezone, \DateTimeImmutable $now): \DateTimeInterface
    {
        if ($timezone) {
            $now = $now->setTimezone(new \DateTimeZone($timezone));
        }

        [$hour, $minute] = explode(':', $time);

        return $now->setTime((int) $hour, (int) $minute);
    }

    private function switchValidationIfToIsSmallerThanFrom(): void
    {
        if ($this->to < $this->from) {
            $tmp = $this->from;
            $this->from = $this->to;
            $this->to = $tmp;
            $this->validationTurnover = true;
        }
    }

    private function returnResultWithSightOnValidationTurnover(\DateTimeImmutable $now): bool
    {
        $result = $this->to >= $now && $this->from <= $now;

        if ($this->validationTurnover) {
            return !$result;
        }

        return $result;
    }
}
