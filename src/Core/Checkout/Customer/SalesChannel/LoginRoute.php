<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/login', name: 'store-api.account.login', methods: ['POST'])]
    public function login(#[\SensitiveParameter] #[MapRequestPayload] LoginCustomerRequestDTO $data, SalesChannelContext $context): Response
    {
        if ($this->requestStack->getMainRequest() !== null) {
            $cacheKey = strtolower($data->username) . '-' . $this->requestStack->getMainRequest()->getClientIp();

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
        }

        $token = $this->accountService->loginByCredentials(
            $data->username,
            $data->password,
            $context
        );

        if (isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $cacheKey);
        }

        $response = new JsonResponse(new LoginCustomerResponseDTO());
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        return $response;
    }
}
