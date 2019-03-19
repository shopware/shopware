<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class QueryDataBagResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === QueryDataBag::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): ?\Generator
    {
        yield new QueryDataBag($request->query->all());
    }
}
