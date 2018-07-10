<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class RestContextValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === RestContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        yield new RestContext(
            $request,
            $context
        );
    }
}
