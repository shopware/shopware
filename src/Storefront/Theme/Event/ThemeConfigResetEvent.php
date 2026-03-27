<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class ThemeConfigResetEvent extends Event implements ShopwareEvent
{
    public function __construct(
        private readonly string $themeId,
        private readonly Context $context
    ) {
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
