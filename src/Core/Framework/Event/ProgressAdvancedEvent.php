<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class ProgressAdvancedEvent extends Event
{
    final public const NAME = self::class;

    public function __construct(private readonly int $step = 1)
    {
    }

    public function getStep(): int
    {
        return $this->step;
    }
}
