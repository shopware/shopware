<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class PreWriteValidationEvent extends Event implements ShopwareEvent
{
    /**
     * @param WriteCommand[] $commands
     */
    public function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands
    ) {
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

    public function getPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity);
    }

    public function getDeletedPrimaryKeys(string $entity): array
    {
        return $this->findPrimaryKeys($entity, fn (WriteCommand $command) => $command instanceof DeleteCommand);
    }

    private function findPrimaryKeys(string $entity, ?\Closure $closure = null): array
    {
        $ids = [];

        foreach ($this->commands as $command) {
            if ($command->getEntityName() !== $entity) {
                continue;
            }

            if ($closure instanceof \Closure && !$closure($command)) {
                continue;
            }

            $ids[] = $command->getPrimaryKey();
        }

        return $ids;
    }
}
