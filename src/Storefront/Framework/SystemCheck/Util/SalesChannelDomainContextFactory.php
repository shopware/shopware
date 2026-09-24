<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\SystemCheck\Util;

use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * Creates the sales channel context an anonymous visitor gets on the given domain, so the readiness checks
 * can pick the page to probe with the same criteria processing the probe request is answered with.
 */
#[Package('discovery')]
class SalesChannelDomainContextFactory
{
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly CartFactory $cartFactory,
    ) {
    }

    /**
     * @description Language, currency and domain are taken from the domain whose URL is probed, not from the
     * sales channel defaults, because all of them feed the criteria processing. The rule IDs are detected the
     * same way SalesChannelContextService::get() detects them for a visitor without a cart: by calculating a
     * new cart. It is created through the CartFactory like the request's cart, so extensions seeding a new cart
     * via the CartCreatedEvent affect the matching rules here as well. The cart only lives in memory: persistence is skipped explicitly, because a cart processor
     * adding a line item or an error would otherwise store a cart under a token nobody uses again. Unlike
     * SalesChannelContextService::get() this does not touch the current request, its session or the cart
     * service, which matters when the check runs inside an Admin API request.
     */
    public function create(SalesChannelDomain $domain): SalesChannelContext
    {
        $token = Uuid::randomHex();

        $context = $this->salesChannelContextFactory->create($token, $domain->salesChannelId, [
            SalesChannelContextService::DOMAIN_ID => $domain->id,
            SalesChannelContextService::LANGUAGE_ID => $domain->languageId,
            SalesChannelContextService::CURRENCY_ID => $domain->currencyId,
        ]);

        $behavior = new CartBehavior([
            ...$context->getPermissions(),
            CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
        ]);

        $this->cartRuleLoader->loadByCart($context, $this->cartFactory->createNew($token), $behavior, true);

        return $context;
    }
}
