<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class StorefrontApiContextValueResolver implements ArgumentValueResolverInterface
{
    public const CONTEXT_TOKEN_KEY = 'x-context-token';

    /**
     * @var StorefrontApiContextService
     */
    private $contextLoader;

    public function __construct(StorefrontApiContextService $contextLoader)
    {
        $this->contextLoader = $contextLoader;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === StorefrontApiContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $applicationId = $request->attributes->get('_shop_id');

        $token = $request->headers->get(self::CONTEXT_TOKEN_KEY);

        yield $this->contextLoader->load($applicationId, $token);
    }
}
