<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Message;

/**
 * @deprecated tag:v6.5.0 - reason:remove-decorator - will be removed, use default symfony queue commands
 */
class SleepMessage
{
    private float $sleepTime;

    private bool $throwError;

    /**
     * @internal
     */
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
