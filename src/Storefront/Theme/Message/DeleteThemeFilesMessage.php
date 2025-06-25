<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * used to delay the deletion of theme files
 *
 * @deprecated tag:v6.8.0 - Will be removed. Unused theme files are now deleted with a scheduled task.
 * @see \Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTask
 * @see \Shopware\Storefront\Theme\ScheduledTask\DeleteThemeFilesTaskHandler
 */
#[Package('framework')]
class DeleteThemeFilesMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $themePath,
        private readonly string $salesChannelId,
        private readonly string $themeId
    ) {
    }

    // @phpstan-ignore-next-line shopware.deprecatedClass - Deprecations for 6.8.0.0 should only be soft
    public function getThemePath(): string
    {
        return $this->themePath;
    }

    // @phpstan-ignore-next-line shopware.deprecatedClass - Deprecations for 6.8.0.0 should only be soft
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    // @phpstan-ignore-next-line shopware.deprecatedClass - Deprecations for 6.8.0.0 should only be soft
    public function getThemeId(): string
    {
        return $this->themeId;
    }
}
