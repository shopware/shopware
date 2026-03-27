<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class ThemeConfigChangedEvent extends Event implements ShopwareEvent
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly string $themeId,
        protected array $config,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
