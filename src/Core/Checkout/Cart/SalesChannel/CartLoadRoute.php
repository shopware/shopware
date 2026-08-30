<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartLoadRoute extends AbstractCartLoadRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $persister,
        private readonly CartFactory $cartFactory,
        private readonly CartCalculator $cartCalculator,
        private readonly TaxProviderProcessor $taxProviderProcessor
    ) {
    }

    public function getDecorated(): AbstractCartLoadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * `$cart` is filled by the CartValueResolver when this route runs as a controller, and then holds the
     * cart that resolving the sales channel context already loaded and calculated for the context token.
     */
    #[Route(path: '/store-api/checkout/cart', name: 'store-api.checkout.cart.read', methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $context, ?Cart $cart = null): CartResponse
    {
        $token = RequestParamHelper::get($request, 'token', $context->getToken());
        $taxed = RequestParamHelper::get($request, 'taxed', false);

        $cart = $cart !== null && $cart->getToken() === $token
            ? $this->cartCalculator->finalize($cart, $context)
            : $this->loadAndCalculate($token, $context);

        if ($taxed) {
            $this->taxProviderProcessor->process($cart, $context);
        }

        return new CartResponse($cart);
    }

    private function loadAndCalculate(string $token, SalesChannelContext $context): Cart
    {
        try {
            $cart = $this->persister->load($token, $context);
        } catch (CartTokenNotFoundException) {
            $cart = $this->cartFactory->createNew($token);
        }

        return $this->cartCalculator->calculate($cart, $context);
    }
}
