<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class PreWriteValidationEvent extends Event implements ShopwareEvent
{
    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var WriteCommand[]
     */
    private $commands;

    public function __construct(WriteContext $writeContext, array $commands)
    {
        $this->writeContext = $writeContext;
        $this->commands = $commands;
    }

    public function getContext(): Context
    {
        return $this->writeContext->getContext();
    }

    public function getWriteContext(): WriteContext
    {
        return $this->writeContext;
    }

    /**
     * @return WriteCommand[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getExceptions(): WriteException
    {
        return $this->writeContext->getExceptions();
    }
}
