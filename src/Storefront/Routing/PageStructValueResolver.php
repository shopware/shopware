<?php declare(strict_types=1);

namespace Shopware\Storefront\Routing;

use Shopware\Storefront\PageStruct\PageStructInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class PageStructValueResolver implements ArgumentValueResolverInterface
{
    /**
     * Whether this resolver can resolve the value for the given ArgumentMetadata.
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return is_subclass_of($argument->getType(), PageStructInterface::class);
    }

    /**
     * Returns the possible value(s).
     */
    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $class = $argument->getType();

        /** @var PageStructInterface $pageStruct */
        $pageStruct = new $class();
        yield $pageStruct->fromRequest($request);
    }
}
