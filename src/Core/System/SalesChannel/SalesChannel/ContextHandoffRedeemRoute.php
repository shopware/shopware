<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [
    PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
    PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => false,
])]
class ContextHandoffRedeemRoute extends AbstractContextHandoffRedeemRoute
{
    final public const TOKEN = 'token';

    /**
     * @internal
     */
    public function __construct(
        private readonly ContextHandoffTokenGenerator $tokenGenerator,
        private readonly ContextHandoffTokenStore $tokenStore,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDecorated(): AbstractContextHandoffRedeemRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context/handoff/redeem', name: 'store-api.context.handoff.redeem', methods: [Request::METHOD_POST])]
    public function redeem(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $handoffToken = $this->tokenGenerator->decode($data->getString(self::TOKEN));

        if ($handoffToken->salesChannelId !== $context->getSalesChannelId()) {
            throw SalesChannelException::contextHandoffSalesChannelMismatch();
        }

        $jti = (string) $handoffToken->jti;
        $contextToken = $this->tokenStore->consume($jti);

        if ($contextToken === null) {
            $this->logger->warning('Rejected context handoff token that is expired or was already consumed.', [
                'jti' => $jti,
                'salesChannelId' => $context->getSalesChannelId(),
            ]);

            throw SalesChannelException::contextHandoffTokenExpiredOrConsumed();
        }

        return new ContextTokenResponse($contextToken);
    }
}
