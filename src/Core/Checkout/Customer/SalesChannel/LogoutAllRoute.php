<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('checkout')]
class LogoutAllRoute extends AbstractLogoutAllRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly SalesChannelContextPersister $contextPersister,
    ) {
    }

    public function getDecorated(): AbstractLogoutAllRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/logout/all',
        name: 'store-api.account.logout.all',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function logout(SalesChannelContext $context, RequestDataBag $data): ContextTokenResponse
    {
        $contextTokenResponse = $this->logoutRoute->logout($context, $data);

        $customerId = $context->getCustomerId();
        \assert($customerId !== null);

        $this->contextPersister->revokeAllCustomerTokens($customerId);

        return $contextTokenResponse;
    }
}
