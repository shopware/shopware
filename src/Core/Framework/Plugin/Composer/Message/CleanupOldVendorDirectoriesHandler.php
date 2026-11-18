<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Composer\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\ComposerWorkingCopy;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class CleanupOldVendorDirectoriesHandler
{
    public function __construct(private ComposerWorkingCopy $workingCopy)
    {
    }

    public function __invoke(CleanupOldVendorDirectories $message): void
    {
        $this->workingCopy->removeOldVendorDirectories();
    }
}
