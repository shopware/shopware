<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Doctrine\DBAL\ConnectionException;
use Shopware\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface SyncServiceInterface
{
    /**
     * @param SyncOperation[] $operations
     *
     * @throws ConnectionException
     * @throws InvalidSyncOperationException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult;
}
