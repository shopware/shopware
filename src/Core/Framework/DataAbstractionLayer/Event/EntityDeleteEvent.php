<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event allows you to hook in to the process of removing an entity. You have the possibility to execute code before and after the entity is removed via
 * the success and error callbacks. You can call the `addSuccess` or `addError` methods with a Closure.
 *
 * You can use this event to capture state and perform actions after an entity is removed. For example, you could collect the entity name before it is deleted, then after it is deleted,
 * use the name to remove the respective data from a third party system via an API call.
 */
#[Package('core')]
class EntityDeleteEvent extends Event implements ShopwareEvent
{
    /**
     * @var list<\Closure>
     */
    private array $successCallbacks = [];

    /**
     * @var list<\Closure>
     */
    private array $errorCallbacks = [];

    /**
     * @var array<string, list<array<string, string>|string>>
     */
    private array $ids = [];

    /**
     * @param array<WriteCommand> $commands
     */
    final private function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands
    ) {
    }

    /**
     * @param array<WriteCommand> $commands
     */
    public static function create(WriteContext $writeContext, array $commands): self
    {
        $deleteCommands = \array_filter($commands, static fn (WriteCommand $command) => $command instanceof DeleteCommand);

        return new static($writeContext, $deleteCommands);
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
     * @return array<WriteCommand>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return array<array<string, string>|string>
     */
    public function getIds(string $entity): array
    {
        if (\array_key_exists($entity, $this->ids)) {
            return $this->ids[$entity];
        }

        $ids = [];

        foreach ($this->getCommands() as $entityWriteResult) {
            if ($entityWriteResult->getEntityName() !== $entity) {
                continue;
            }

            $primaryKeys = $entityWriteResult->getDecodedPrimaryKey();

            if (\count($primaryKeys) === 1) {
                $ids[] = reset($primaryKeys);
                continue;
            }

            $ids[] = $primaryKeys;
        }

        return $this->ids[$entity] = $ids;
    }

    public function filled(): bool
    {
        return \count($this->commands) > 0;
    }

    public function addSuccess(\Closure $callback): void
    {
        $this->successCallbacks[] = $callback;
    }

    public function addError(\Closure $callback): void
    {
        $this->errorCallbacks[] = $callback;
    }

    public function success(): void
    {
        foreach ($this->successCallbacks as $callback) {
            $callback();
        }
    }

    public function error(): void
    {
        foreach ($this->errorCallbacks as $callback) {
            $callback();
        }
    }
}
