<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class CustomerValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === CustomerEntity::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if (!$loginRequired instanceof LoginRequired) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing @LoginRequired annotation for route: ' . $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            $route = $request->attributes->get('_route');

            throw new \RuntimeException('Missing sales channel context for route ' . $route);
        }

        yield $context->getCustomer();
    }
}
