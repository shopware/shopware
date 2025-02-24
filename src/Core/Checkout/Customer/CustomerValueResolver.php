<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('checkout')]
class CustomerValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== CustomerEntity::class) {
            return;
        }

        $loginRequired = $request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED);

        if ($loginRequired !== true) {
            $route = $request->attributes->get('_route');

            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new \RuntimeException('Missing @LoginRequired annotation for route: ' . $route);
            }
            throw CustomerException::missingRouteAnnotation('LoginRequired', $route);
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            $route = $request->attributes->get('_route');

            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new \RuntimeException('Missing sales channel context for route ' . $route);
            }
            throw CustomerException::missingRouteSalesChannel($route);
        }

        yield $context->getCustomer();
    }
}
