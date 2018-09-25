<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

abstract class MigrationStep
{
    /**
     * get creation timestamp
     */
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection): void
    {
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection): void
    {
    }

    public function addTrigger(Connection $connection): void
    {
        /** @var Trigger $trigger */
        foreach ($this->getTrigger() as $trigger) {
            $trigger->add($connection, $this->getCreationTimestamp());
        }
    }

    public function removeTrigger(Connection $connection): void
    {
        /** @var Trigger $trigger */
        foreach ($this->getTrigger() as $trigger) {
            $trigger->drop($connection);
        }
    }

    /**
     * @return Trigger[]
     */
    protected function getTrigger(): array
    {
        return [];
    }
}
