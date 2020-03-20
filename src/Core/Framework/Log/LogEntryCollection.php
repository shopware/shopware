<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(LogEntryEntity $entity)
 * @method void                set(string $key, LogEntryEntity $entity)
 * @method LogEntryEntity[]    getIterator()
 * @method LogEntryEntity[]    getElements()
 * @method LogEntryEntity|null get(string $key)
 * @method LogEntryEntity|null first()
 * @method LogEntryEntity|null last()
 */
class LogEntryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_log_entry_collection';
    }

    protected function getExpectedClass(): string
    {
        return LogEntryEntity::class;
    }
}
