<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCodeClaimHandlerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGatewayInterface;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedGlobalCodePromotions;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedIndividualCodePromotions;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Claims codes that match a real, active Shopware promotion, so a plugin's
 * CartCodeClaimHandlerInterface claiming the same code surfaces
 * CartException::ambiguousCodeClaim() instead of silently overriding the promotion.
 */
#[Package('checkout')]
final class PromotionCartCodeClaimHandler implements CartCodeClaimHandlerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PromotionGatewayInterface $gateway,
        private readonly PromotionItemBuilder $itemBuilder,
        private readonly CartService $cartService,
    ) {
    }

    public function claim(string $code, SalesChannelContext $context): bool
    {
        $salesChannelId = $context->getSalesChannelId();

        $globalCriteria = (new Criteria())
            ->addFilter(new PermittedGlobalCodePromotions([$code], $salesChannelId))
            ->setLimit(1);

        if ($this->gateway->get($globalCriteria, $context)->count() > 0) {
            return true;
        }

        $individualCriteria = (new Criteria())
            ->addFilter(new PermittedIndividualCodePromotions([$code], $salesChannelId))
            ->setLimit(1);

        return $this->gateway->get($individualCriteria, $context)->count() > 0;
    }

    public function handle(string $code, Cart $cart, SalesChannelContext $context): Cart
    {
        $lineItem = $this->itemBuilder->buildPlaceholderItem($code);

        return $this->cartService->add($cart, $lineItem, $context);
    }

    public function getSuccessMessage(): ?string
    {
        return null;
    }
}
