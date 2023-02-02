<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProgressFinishedEvent extends Event
{
    public const NAME = self::class;

    /**
     * @var string
     */
    private $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
