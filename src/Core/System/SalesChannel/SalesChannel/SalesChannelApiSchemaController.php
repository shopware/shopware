<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelApiSchemaController extends AbstractController
{
    /**
     * @var DefinitionService
     */
    protected $definitionService;

    public function __construct(DefinitionService $definitionService)
    {
        $this->definitionService = $definitionService;
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/openapi3.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="sales-channel-api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     */
    public function info(int $version): JsonResponse
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::SALES_CHANNEL_API, $version);

        return $this->json($data);
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/open-api-schema.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="sales-channel-api.info.open-api-schema", methods={"GET"})
     */
    public function openApiSchema(int $version): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::SALES_CHANNEL_API, $version);

        return $this->json($data);
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/swagger.html", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="sales-channel-api.info.swagger", methods={"GET"})
     */
    public function infoHtml(int $version): Response
    {
        return $this->render('@Framework/swagger.html.twig', ['schemaUrl' => 'sales-channel-api.info.openapi3', 'apiVersion' => $version]);
    }
}
