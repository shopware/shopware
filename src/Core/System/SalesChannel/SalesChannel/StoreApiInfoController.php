<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('sales-channel')]
class StoreApiInfoController
{
    /**
     * @internal
     *
     * @param array{administration?: string} $cspTemplates
     */
    public function __construct(
        protected DefinitionService $definitionService,
        private readonly Environment $twig,
        private readonly array $cspTemplates
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
}
