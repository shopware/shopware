<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @RouteScope(scopes={"store-api"})
 */
class StoreApiInfoController
{
    /**
     * @var DefinitionService
     */
    protected $definitionService;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var array
     */
    private $cspTemplates;

    public function __construct(DefinitionService $definitionService, Environment $twig, array $cspTemplates)
    {
        $this->definitionService = $definitionService;
        $this->twig = $twig;
        $this->cspTemplates = $cspTemplates;
    }

    /**
     * @Route("/store-api/v{version}/_info/openapi3.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="store-api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     */
    public function info(int $version): JsonResponse
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::STORE_API, $version);

        return new JsonResponse($data);
    }

    /**
     * @Route("/store-api/v{version}/_info/open-api-schema.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="store-api.info.open-api-schema", methods={"GET"})
     */
    public function openApiSchema(int $version): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::STORE_API, $version);

        return new JsonResponse($data);
    }

    /**
     * @Route("/store-api/v{version}/_info/swagger.html", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="store-api.info.swagger", methods={"GET"})
     */
    public function infoHtml(Request $request, int $version): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $response = new Response($this->twig->render(
            '@Framework/swagger.html.twig',
            [
                'schemaUrl' => 'store-api.info.openapi3',
                'apiVersion' => $version,
                'cspNonce' => $nonce,
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
