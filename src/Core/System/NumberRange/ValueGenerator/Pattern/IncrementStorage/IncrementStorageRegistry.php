<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage;

use Shopware\Core\System\NumberRange\Exception\IncrementStorageMigrationNotSupportedException;
use Shopware\Core\System\NumberRange\Exception\IncrementStorageNotFoundException;

class IncrementStorageRegistry
{
    /**
     * @var IncrementStorageInterface[]|AbstractIncrementStorage[]
     */
    private array $storages;

    private string $configuredStorage;

    /**
     * @internal
     */
    public function __construct(\Traversable $storages, string $configuredStorage)
    {
        $this->storages = iterator_to_array($storages);
        $this->configuredStorage = $configuredStorage;
    }

    /**
     * @return IncrementStorageInterface|AbstractIncrementStorage
     *
     * @deprecated tag:v6.5.0 - reason:return-type-change - will always return AbstractIncrementStorage in the future, and thus will be natively typed
     */
    public function getStorage(?string $storage = null)/*: AbstractIncrementStorage*/
    {
        if ($storage === null) {
            $storage = $this->configuredStorage;
        }

        if (!isset($this->storages[$storage])) {
            throw new IncrementStorageNotFoundException($storage, array_keys($this->storages));
        }

        return $this->storages[$storage];
    }

    public function migrate(string $from, string $to): void
    {
        $fromStorage = $this->getStorage($from);
        if (!$fromStorage instanceof AbstractIncrementStorage) {
            throw new IncrementStorageMigrationNotSupportedException($from);
        }
        $toStorage = $this->getStorage($to);
        if (!$toStorage instanceof AbstractIncrementStorage) {
            throw new IncrementStorageMigrationNotSupportedException($to);
        }

        foreach ($fromStorage->list() as $numberRangeId => $state) {
            $toStorage->set($numberRangeId, $state);
        }
    }
}
