<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontRequestDataBagResolver implements ValueResolverInterface
{
    /**
     * @return \Generator<RequestDataBag>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== RequestDataBag::class) {
            return;
        }

        if (!$this->isStorefrontRequest($request)) {
            return;
        }

        yield new RequestDataBag($this->trimStrings($request->request->all()));
    }

    private function isStorefrontRequest(Request $request): bool
    {
        $routeScopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return \in_array(StorefrontRouteScope::ID, (array) $routeScopes, true);
    }

    /**
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    private function trimStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_string($key) && \str_contains(\strtolower($key), 'password')) {
                continue;
            }

            if (\is_string($value)) {
                $data[$key] = trim($value);

                continue;
            }

            if (\is_array($value)) {
                $data[$key] = $this->trimStrings($value);
            }
        }

        return $data;
    }
}
