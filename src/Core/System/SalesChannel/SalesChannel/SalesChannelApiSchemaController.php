<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\SalesChannel;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\Exception\ApiBrowserNotPublicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SalesChannelApiSchemaController extends AbstractController
{
    /**
     * @var DefinitionService
     */
    protected $definitionService;

    /**
     * @var bool
     */
    private $isApiBrowserPublic;

    public function __construct(DefinitionService $definitionService, bool $isApiBrowserPublic)
    {
        $this->definitionService = $definitionService;
        $this->isApiBrowserPublic = $isApiBrowserPublic;
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/openapi3.json", name="sales-channel-api.info.openapi3", methods={"GET"}, defaults={"auth_required"=false})
     *
     * @throws \Exception
     */
    public function info(): JsonResponse
    {
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::SALES_CHANNEL_API);

        return $this->json($data);
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/open-api-schema.json", name="sales-channel-api.info.open-api-schema", methods={"GET"}, defaults={"auth_required"=false})
     */
    public function openApiSchema(): JsonResponse
    {
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::SALES_CHANNEL_API);

        return $this->json($data);
    }

    /**
     * @Route("/sales-channel-api/v{version}/_info/swagger.html", name="sales-channel-api.info.swagger", methods={"GET"}, defaults={"auth_required"=false})
     */
    public function infoHtml(): Response
    {
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

        return $this->render('@Framework/swagger.html.twig', ['schemaUrl' => 'sales-channel-api.info.openapi3']);
    }
}
