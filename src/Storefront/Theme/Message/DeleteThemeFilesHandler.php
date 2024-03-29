<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
#[Package('storefront')]
final class DeleteThemeFilesHandler
{
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly AbstractThemePathBuilder $pathBuilder
    ) {
    }

    public function __invoke(DeleteThemeFilesMessage $message): void
    {
        $currentPath = $this->pathBuilder->assemblePath($message->getSalesChannelId(), $message->getThemeId());

        if ($currentPath === $message->getThemePath()) {
            return;
        }

        $this->filesystem->deleteDirectory('theme' . \DIRECTORY_SEPARATOR . $message->getThemePath());
    }
}
