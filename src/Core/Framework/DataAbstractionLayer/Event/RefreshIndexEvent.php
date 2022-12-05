<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package system-settings
 *
 * @internal
 */
class RefreshIndexEvent extends Event
{
    private bool $useQueue;

    public function __construct(bool $useQueue)
    {
        $this->useQueue = $useQueue;
    }

    public function getUseQueue(): bool
    {
        return $this->useQueue;
    }
}
