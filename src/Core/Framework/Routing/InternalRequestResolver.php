<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class InternalRequestResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === InternalRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): ?Generator
    {
        yield InternalRequest::createFromHttpRequest($request);
    }
}
