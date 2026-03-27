<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event allows you to hook in to the process of writing an entity. This includes, creating, updating and deleting entities. You have the possibility to execute code before and after the entity is written via
 * the success and error callbacks. You can call the `addSuccess` or `addError` methods with any PHP callable.
 *
 * You can use this event to capture state and perform actions and sync data after an entity is written. It could be used for example, to synchronize images to a CDN when they are written, updated or deleted. This event is useful when you need the before state of the entity. For example, the old filename.
 */
#[Package('framework')]
class EntityWriteEvent extends Event implements ShopwareEvent
{
    /**
     * @var list<callable(): void>
     */
    private array $successCallbacks = [];

    /**
     * @var list<callable(): void>
     */
    private array $errorCallbacks = [];

    /**
     * @var array<string, list<array<string, string>|string>>
     */
    private array $ids = [];

    /**
     * @param list<WriteCommand> $commands
     */
    private function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands
    ) {
    }

    /**
     * @param list<WriteCommand> $commands
     */
    public static function create(WriteContext $writeContext, array $commands): self
    {
        return new self($writeContext, $commands);
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
     * @return list<WriteCommand>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return list<WriteCommand>
     */
    public function getCommandsForEntity(string $entityName): array
    {
        return array_values(array_filter(
            $this->commands,
            static fn (WriteCommand $command) => $command->getEntityName() === $entityName
        ));
    }

    /**
     * @return list<array<string, string>|string>
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

    /**
     * @param callable(): void $callback
     */
    public function addSuccess(callable $callback): void
    {
        $this->successCallbacks[] = $callback;
    }

    /**
     * @param callable(): void $callback
     */
    public function addError(callable $callback): void
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
