<?php

namespace Shopware\Core\Framework\Adapter\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class SentAtStamp implements StampInterface
{
    private int $sentAt;

    public function __construct(int $sentAt)
    {
        $this->sentAt = $sentAt;
    }

    public function getSentAt(): int
    {
        return $this->sentAt;
    }
}
