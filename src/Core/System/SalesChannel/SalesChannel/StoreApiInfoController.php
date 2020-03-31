<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    public function __construct(DefinitionService $definitionService, Environment $twig)
    {
        $this->definitionService = $definitionService;
        $this->twig = $twig;
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
    public function infoHtml(int $version): Response
    {
        $content = $this->twig->render('@Framework/swagger.html.twig', ['schemaUrl' => 'store-api.info.openapi3', 'apiVersion' => $version]);

        return new Response($content);
    }
}
