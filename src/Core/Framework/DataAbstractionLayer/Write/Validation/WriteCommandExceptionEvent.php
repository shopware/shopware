<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class WriteCommandExceptionEvent extends Event implements ShopwareEvent
{
    private \Throwable $exception;

    /**
     * @var WriteCommand[]
     */
    private array $commands;

    private Context $context;

    public function __construct(\Throwable $exception, array $commands, Context $context)
    {
        $this->exception = $exception;
        $this->commands = $commands;
        $this->context = $context;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
