<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Stamp;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[Package('framework')]
readonly class SentAtStamp implements StampInterface
{
    private \DateTimeInterface $sentAt;

    public function __construct(?\DateTimeInterface $sentAt = null, ClockInterface $clock = new NativeClock())
    {
        $this->sentAt = $sentAt ?? $clock->now();
    }

    public function getSentAt(): \DateTimeInterface
    {
        return $this->sentAt;
    }
}
