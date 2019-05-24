<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CartValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(RequestStack $requestStack, CartService $cartService)
    {
        $this->requestStack = $requestStack;
        $this->cartService = $cartService;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === Cart::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            $master = $request;
        }

        /** @var SalesChannelContext $context */
        $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        yield $this->cartService->getCart($context->getToken(), $context);
    }
}
