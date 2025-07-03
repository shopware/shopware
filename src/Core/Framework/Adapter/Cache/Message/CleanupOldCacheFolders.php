<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

#[Package('framework')]
class CleanupOldCacheFolders implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    public function deduplicationId(): ?string
    {
        return 'cleanup-old-cache-folders';
    }
}
