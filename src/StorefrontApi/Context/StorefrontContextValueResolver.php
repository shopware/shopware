<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Shopware\Context\Struct\StorefrontContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class StorefrontContextValueResolver implements ArgumentValueResolverInterface
{
    public const CONTEXT_TOKEN_KEY = 'x-context-token';

    public const APPLICATION_ID = 'x-application-id';

    /**
     * @var StorefrontContextService
     */
    private $contextService;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(StorefrontContextService $contextService, RequestStack $requestStack)
    {
        $this->contextService = $contextService;
        $this->requestStack = $requestStack;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === StorefrontContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            $master = $request;
        }

        $token = $master->attributes->get(self::CONTEXT_TOKEN_KEY);

        $applicationId = $master->attributes->get(self::APPLICATION_ID);

        $context = $this->contextService->getStorefrontContext($applicationId, $token);

        yield $context;
    }
}
