<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ResponseFactoryInterfaceValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var ResponseFactoryRegistry
     */
    private $responseTypeRegistry;

    public function __construct(ResponseFactoryRegistry $responseTypeRegistry)
    {
        $this->responseTypeRegistry = $responseTypeRegistry;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ResponseFactoryInterface::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        yield $this->responseTypeRegistry->getType($request);
    }
}
