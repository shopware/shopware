<?php declare(strict_types=1);

namespace Shopware\Framework\Routing;

use Shopware\Checkout\CustomerContext;
use Shopware\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class StorefrontContextValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === CustomerContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            $master = $request;
        }

        yield $master->attributes->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);
    }
}
