<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

class SleepMessage
{
    private float $sleepTime;

    private bool $throwError;

    public function __construct(float $sleepTime, bool $throwError = false)
    {
        $this->sleepTime = $sleepTime;
        $this->throwError = $throwError;
    }

    public function getSleepTime(): float
    {
        return $this->sleepTime;
    }

    public function isThrowError(): bool
    {
        return $this->throwError;
    }
}
