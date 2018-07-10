<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ContextValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === Context::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
    }
}
