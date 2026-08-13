<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 *
 * used to compile the themes in the queue
 */
#[Package('discovery')]
class CompileThemeMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $salesChannelId,
        private readonly string $themeId,
        private readonly bool $withAssets,
        private readonly Context $context,
        private readonly bool $assign = false
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function isWithAssets(): bool
    {
        return $this->withAssets;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Whether to assign the theme to the sales channel once compiled. Defers a theme
     * switch until its files exist so the storefront never renders without CSS.
     */
    public function isAssign(): bool
    {
        return $this->assign;
    }
}
