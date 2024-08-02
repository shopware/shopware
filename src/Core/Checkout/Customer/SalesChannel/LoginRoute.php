<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/login', name: 'store-api.account.login', methods: ['POST'])]
    public function login(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        EmailIdnConverter::encodeDataBag($data);
        $email = (string) $data->get('email', $data->get('username'));

        if ($this->requestStack->getMainRequest() !== null) {
            $cacheKey = strtolower($email) . '-' . $this->requestStack->getMainRequest()->getClientIp();

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
        }

        $token = $this->accountService->loginByCredentials(
            $email,
            (string) $data->get('password'),
            $context
        );

        if (isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $cacheKey);
        }

        return new ContextTokenResponse($token);
    }
}
