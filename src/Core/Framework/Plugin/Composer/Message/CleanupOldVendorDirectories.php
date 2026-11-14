<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

#[Package('framework')]
class CleanupOldVendorDirectories implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @experimental stableVersion:v6.8.0 feature:DEDUPLICATABLE_MESSAGES
     */
    public function deduplicationId(): ?string
    {
        return 'cleanup-old-vendor-directories';
    }
}
