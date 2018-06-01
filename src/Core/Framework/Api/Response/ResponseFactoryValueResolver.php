<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ResponseFactoryValueResolver implements ArgumentValueResolverInterface
{
    /**
     * @var ResponseFactory
     */
    private $jsonApiFactory;

    public function __construct(ResponseFactory $jsonApiFactory)
    {
        $this->jsonApiFactory = $jsonApiFactory;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ResponseFactory::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $this->jsonApiFactory;
    }
}
