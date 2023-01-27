<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class BeforeDeleteEvent extends Event implements ShopwareEvent
{
    /**
     * @var \Closure[]
     */
    private array $successCallbacks = [];

    /**
     * @var \Closure[]
     */
    private array $errorCallbacks = [];

    private array $ids = [];

    /**
     * @param WriteCommand[] $commands
     */
    private function __construct(
        private readonly WriteContext $writeContext,
        private readonly array $commands
    ) {
    }

    public static function create(WriteContext $writeContext, array $commands): self
    {
        $deleteCommands = \array_filter($commands, static fn (WriteCommand $command) => $command instanceof DeleteCommand);

        return new self($writeContext, $deleteCommands);
    }

    public function getContext(): Context
    {
        return $this->writeContext->getContext();
    }

    public function getWriteContext(): WriteContext
    {
        return $this->writeContext;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getIds(string $entity): array
    {
        if (\array_key_exists($entity, $this->ids)) {
            return $this->ids[$entity];
        }

        $ids = [];

        foreach ($this->getCommands() as $entityWriteResult) {
            $definition = $entityWriteResult->getDefinition();

            if ($definition->getEntityName() !== $entity) {
                continue;
            }

            $primaryKeys = $definition->getPrimaryKeys()->filter(static fn (Field $field) => !$field instanceof VersionField
                && !$field instanceof ReferenceVersionField
                && $field instanceof StorageAware);

            $ids[] = $this->getCommandPrimaryKey($entityWriteResult, $primaryKeys);
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

    private function getCommandPrimaryKey(WriteCommand $command, FieldCollection $fields): array|string
    {
        $primaryKey = $command->getPrimaryKey();

        $data = [];

        if ($fields->count() === 1) {
            /** @var StorageAware $field */
            $field = $fields->first();

            return Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        foreach ($fields as $field) {
            if (!$field instanceof StorageAware) {
                continue;
            }

            $data[$field->getPropertyName()] = Uuid::fromBytesToHex($primaryKey[$field->getStorageName()]);
        }

        return $data;
    }
}
