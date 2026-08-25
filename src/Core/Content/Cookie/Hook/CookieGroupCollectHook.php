<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Hook;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the cookie consent groups are collected for the current sales channel.
 * Allows apps to modify or remove cookie groups and entries, e.g. depending on the payment methods active in the current sales channel.
 *
 * @hook-use-case data_loading
 *
 * @since 6.7.14.0
 *
 * @final
 */
#[Package('discovery')]
class CookieGroupCollectHook extends Hook implements SalesChannelContextAware
{
    use SalesChannelContextAwareTrait;

    final public const HOOK_NAME = 'cookie-group-collect';

    /**
     * @internal
     */
    public function __construct(
        private readonly CookieGroupCollection $cookieGroups,
        SalesChannelContext $salesChannelContext,
    ) {
        parent::__construct($salesChannelContext->getContext());
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->cookieGroups;
    }

    public function getName(): string
    {
        return self::HOOK_NAME;
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
        ];
    }
}
