<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class WriteCommandExceptionEvent extends Event implements ShopwareEvent
{
    /**
     * @param WriteCommand[] $commands
     */
    public function __construct(
        private readonly \Throwable $exception,
        private readonly array $commands,
        private readonly Context $context
    ) {
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
