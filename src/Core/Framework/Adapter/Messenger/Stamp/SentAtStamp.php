<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Messenger\Stamp;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[Package('core')]
readonly class SentAtStamp implements StampInterface
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
