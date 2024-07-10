<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\Api\Route\RouteInfo;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('buyers-experience')]
class StoreApiInfoController
{
    private const API_SCOPE_STORE = 'store-api';

    /**
     * @internal
     *
     * @param array{administration?: string} $cspTemplates
     */
    public function __construct(
        protected DefinitionService $definitionService,
        private readonly Environment $twig,
        private readonly array $cspTemplates,
        private readonly ApiRouteInfoResolver $apiRouteInfoResolver,
    ) {
    }

    #[Route(path: '/store-api/_info/openapi3.json', defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'], name: 'store-api.info.openapi3', methods: ['GET'])]
    public function info(Request $request): JsonResponse
    {
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);

        $apiType = $this->definitionService->toApiType($apiType);
        if ($apiType === null) {
            throw RoutingException::invalidRequestParameter('type');
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::STORE_API, $apiType);

        return new JsonResponse($data);
    }

    #[Route(path: '/store-api/_info/open-api-schema.json', defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'], name: 'store-api.info.open-api-schema', methods: ['GET'])]
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::STORE_API);

        return new JsonResponse($data);
    }

    #[Route(path: '/store-api/_info/swagger.html', defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'], name: 'store-api.info.swagger', methods: ['GET'])]
    /**
     * @deprecated tag:v6.7.0 - Will be removed in v6.7.0. Use store-api.info.stoplightio instead
     */
    public function infoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);
        $response = new Response($this->twig->render(
            '@Framework/swagger.html.twig',
            [
                'schemaUrl' => 'store-api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        ));

        $cspTemplate = $this->cspTemplates['administration'] ?? '';
        $cspTemplate = trim($cspTemplate);
        if ($cspTemplate !== '') {
            $csp = str_replace('%nonce%', $nonce, $cspTemplate);
            $csp = str_replace(["\n", "\r"], ' ', $csp);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(path: '/store-api/_info/stoplightio.html', defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'], name: 'store-api.info.stoplightio', methods: ['GET'])]
    public function stoplightIoInfoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);
        $response = new Response($this->twig->render(
            '@Framework/stoplightio.html.twig',
            [
                'schemaUrl' => 'store-api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        ));

        $cspTemplate = $this->cspTemplates['administration'] ?? '';
        $cspTemplate = trim($cspTemplate);
        if ($cspTemplate !== '') {
            $csp = str_replace('%nonce%', $nonce, $cspTemplate);
            $csp = str_replace(["\n", "\r"], ' ', $csp);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(path: '/store-api/_info/routes', name: 'store-api.info.routes', methods: ['GET'], defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'])]
    public function getRoutes(): JsonResponse
    {
        $endpoints = array_map(
            fn (RouteInfo $endpoint) => ['path' => $endpoint->path, 'methods' => $endpoint->methods],
            $this->apiRouteInfoResolver->getApiRoutes(self::API_SCOPE_STORE)
        );

        return new JsonResponse(['endpoints' => $endpoints]);
    }
}
