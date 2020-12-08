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

    /**
     * @var \DateTimeInterface|null
     */
    private $now;

    /**
     * @var bool
     */
    private $validationTurnover = false;

    /**
     * @var \DateTime
     */
    private $to;

    /**
     * @var \DateTime
     */
    private $from;

    public function __construct(?\DateTimeInterface $now = null)
    {
        parent::__construct();
        $this->now = $now ?? new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return 'timeRange';
    }

    public function match(RuleScope $scope): bool
    {
        $this->from = $this->extractTime($this->fromTime);
        $this->to = $this->extractTime($this->toTime);

        $this->switchValidationIfToIsSmallerThanFrom();

        return $this->returnResultWithSightOnValidationTurnover();
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

    private function switchValidationIfToIsSmallerThanFrom(): void
    {
        if ($this->to < $this->from) {
            $tmp = $this->from;
            $this->from = $this->to;
            $this->to = $tmp;
            $this->validationTurnover = true;
        }
    }

    private function returnResultWithSightOnValidationTurnover(): bool
    {
        $result = $this->to >= $this->now && $this->from <= $this->now;

        if ($this->validationTurnover) {
            return !$result;
        }

        return $result;
    }
}
