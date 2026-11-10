<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\WorkingCopy;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class CleanupOldVendorDirectoriesHandler
{
    public function __construct(private WorkingCopy $workingCopy)
    {
    }

    public function __invoke(CleanupOldVendorDirectories $message): void
    {
        $this->workingCopy->removeOldVendorDirectories();
    }
}
