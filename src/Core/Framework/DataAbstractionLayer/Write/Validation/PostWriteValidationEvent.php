<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Component\EventDispatcher\Event;

class PostWriteValidationEvent extends Event implements ShopwareEvent
{
    /**
     * @var WriteContext
     */
    private $writeContext;

    /**
     * @var WriteCommandInterface[]
     */
    private $commands;

    public function __construct(WriteContext $writeContext, array $commands)
    {
        $this->writeContext = $writeContext;
        $this->commands = $commands;
    }

    public function getName(): string
    {
        return 'framework.write.validation.post';
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
     * @return WriteCommandInterface[]
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
