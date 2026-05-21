<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\A2A;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Discovery\SalesChannelDomainResolver;
use Shopware\Core\Framework\Ucp\Discovery\UcpConfigProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Serves `/.well-known/agent-card.json` per A2A Protocol §"Agent Cards" so
 * A2A-aware platforms can discover the business agent.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class A2AAgentCardController
{
    public function __construct(
        private readonly SalesChannelDomainResolver $domainResolver,
        private readonly UcpConfigProvider $configProvider,
        private readonly A2AAgentCard $card,
    ) {
    }

    #[Route(
        path: '/.well-known/agent-card.json',
        name: 'ucp.a2a.agent_card',
        defaults: ['auth_required' => false, 'XmlHttpRequest' => true],
        methods: ['GET']
    )]
    public function card(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $context = Context::createDefaultContext();
        $domain = $this->domainResolver->resolve($request, $context);
        if ($domain === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $config = $this->configProvider->forSalesChannel($domain->getSalesChannelId(), $context);
        if ($config === null || !$config->isActive() || !$config->isTransportEnabled('a2a')) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $card = $this->card->build(
            rtrim($domain->getUrl(), '/'),
            $config->getUcpVersion(),
            $config->getEnabledCapabilities()
        );

        $response = new JsonResponse($card);
        $response->headers->set('Cache-Control', 'public, max-age=300');

        return $response;
    }
}
