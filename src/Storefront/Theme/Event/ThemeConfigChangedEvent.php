<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
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
        private readonly ?Context $context = null
    ) {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
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
        // tag:v6.8.0 - Remove this null check, $context will be required
        if ($this->context === null) {
            throw FrameworkException::invalidEventData('No context provided. Pass $context to the constructor of ' . static::class);
        }

        return $this->context;
    }

    /**
     * @deprecated tag:v6.8.0 - Use getContext() instead, $context will be required in the constructor.
     */
    public function getNullableContext(): ?Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'getNullableContext() is deprecated, use getContext() instead.');

        return $this->context;
    }
}
