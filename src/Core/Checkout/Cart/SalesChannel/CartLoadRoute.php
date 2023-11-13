<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
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

    #[Route(path: '/store-api/checkout/cart', name: 'store-api.checkout.cart.read', methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $context): CartResponse
    {
        $token = $request->get('token', $context->getToken());
        $taxed = $request->get('taxed', false);

        try {
            $cart = $this->persister->load($token, $context);
        } catch (CartTokenNotFoundException) {
            $cart = $this->cartFactory->createNew($token);
        }

        $cart = $this->cartCalculator->calculate($cart, $context);

        if ($taxed) {
            $this->taxProviderProcessor->process($cart, $context);
        }

        return new CartResponse($cart);
    }
}
