<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class LookupRoute extends AbstractLookupRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function getDecorated(): AbstractLookupRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/lookup', name: 'store-api.account.lookup', methods: ['POST'])]
    public function lookup(RequestDataBag $data, SalesChannelContext $context): SuccessResponse
    {
        if (!$this->systemConfigService->getBool('core.loginRegistration.allowAccountLookup')) {
            throw CustomerException::accountLookupDisabled();
        }

        EmailIdnConverter::encodeDataBag($data);
        $email = (string) $data->get('email', $data->get('username'));

        if ($this->requestStack->getMainRequest() !== null) {
            $cacheKey = $this->requestStack->getMainRequest()->getClientIp() ?? '';

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOOKUP_ROUTE, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
        }

        return new SuccessResponse($this->accountService->customerExists($email, $context));
    }
}
