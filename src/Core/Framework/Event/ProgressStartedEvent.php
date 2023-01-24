<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
class ProgressStartedEvent extends Event
{
    final public const NAME = self::class;

    public function __construct(private readonly string $message, private readonly int $total)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
