<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package core
 */
class ProgressFinishedEvent extends Event
{
    final public const NAME = self::class;

    public function __construct(private readonly string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
