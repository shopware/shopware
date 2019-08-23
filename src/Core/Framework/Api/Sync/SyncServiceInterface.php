<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Context;

interface SyncServiceInterface
{
    /**
     * @param SyncOperation[] $operations
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function sync(array $operations, Context $context, SyncBehavior $behavior): SyncResult;
}
