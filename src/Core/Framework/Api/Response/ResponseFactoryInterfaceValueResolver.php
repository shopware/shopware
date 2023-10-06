<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[Package('core')]
class ResponseFactoryInterfaceValueResolver implements ValueResolverInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly ResponseFactoryRegistry $responseTypeRegistry)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        if ($argument->getType() !== ResponseFactoryInterface::class) {
            return;
        }

        yield $this->responseTypeRegistry->getType($request);
    }
}
