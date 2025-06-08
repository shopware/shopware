<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAwareTrait;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedHook;

/**
 * Triggered when the AccountOrderDetailPage is loaded
 *
 * @hook-use-case data_loading
 *
 * @since 6.4.8.0
 *
 * @final
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
#[Package('checkout')]
class AccountOrderDetailPageLoadedHook extends PageLoadedHook
{
    use SalesChannelContextAwareTrait {
        getSalesChannelContext as private getSalesChannelContextHook;
    }

    final public const HOOK_NAME = 'account-order-detail-page-loaded';

    public function __construct(
        private readonly AccountOrderDetailPage $page,
        SalesChannelContext $context
    ) {
        parent::__construct($context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0')
        );

        return self::HOOK_NAME;
    }

    public function getPage(): AccountOrderDetailPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0')
        );

        return $this->page;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.8.0.0')
        );

        return $this->getSalesChannelContextHook();
    }
}
