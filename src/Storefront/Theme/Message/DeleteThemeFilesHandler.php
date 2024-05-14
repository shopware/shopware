<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\ThemeScripts;
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
        private readonly SystemConfigService $systemConfig,
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
        $this->systemConfig->delete(ThemeScripts::SCRIPT_FILES_CONFIG_KEY . '.' . $message->getThemePath());
    }
}
