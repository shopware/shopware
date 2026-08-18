<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenGenerator;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;
use Shopware\Core\System\SalesChannel\ContextHandoffTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [
    PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
    PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
])]
class ContextHandoffGenerateRoute extends AbstractContextHandoffGenerateRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContextHandoffTokenGenerator $tokenGenerator,
        private readonly ContextHandoffTokenStore $tokenStore,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractContextHandoffGenerateRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context/handoff/generate', name: 'store-api.context.handoff.generate', methods: [Request::METHOD_POST])]
    public function generate(SalesChannelContext $context): ContextHandoffTokenResponse
    {
        $handoffToken = new ContextHandoffToken();
        $handoffToken->jti = Uuid::randomHex();
        $handoffToken->aud = [ContextHandoffTokenGenerator::AUDIENCE];
        $handoffToken->salesChannelId = $context->getSalesChannelId();
        $handoffToken->exp = $this->clock->now()->add(
            new \DateInterval('PT' . ContextHandoffTokenGenerator::TOKEN_LIFETIME . 'S')
        );

        $handoffJwt = $this->tokenGenerator->encode($handoffToken);

        $this->tokenStore->store(
            $handoffToken->jti,
            $context->getToken(),
            ContextHandoffTokenGenerator::TOKEN_LIFETIME
        );

        return new ContextHandoffTokenResponse($handoffJwt, $handoffToken->exp);
    }
}
