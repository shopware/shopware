<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends AbstractController
{
    /**
     * @var DefinitionService
     */
    private $definitionService;

    /**
     * @var ParameterBagInterface
     */
    private $params;

    /**
     * @var BusinessEventRegistry
     */
    private $actionEventRegistry;

    public function __construct(DefinitionService $definitionService, ParameterBagInterface $params, BusinessEventRegistry $actionEventRegistry)
    {
        $this->definitionService = $definitionService;
        $this->params = $params;
        $this->actionEventRegistry = $actionEventRegistry;
    }

    /**
     * @Route("/api/v{version}/_info/openapi3.json", defaults={"auth_required"=false}, name="api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     */
    public function info(): JsonResponse
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/open-api-schema.json", defaults={"auth_required"=false}, name="api.info.open-api-schema", methods={"GET"})
     */
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/entity-schema.json", defaults={"auth_required"=false}, name="api.info.entity-schema", methods={"GET"})
     */
    public function entitySchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(EntitySchemaGenerator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/swagger.html", defaults={"auth_required"=false}, name="api.info.swagger", methods={"GET"})
     */
    public function infoHtml(): Response
    {
        return $this->render('@Shopware/swagger.html.twig', ['schemaUrl' => 'api.info.openapi3']);
    }

    /**
     * @Route("/api/v{version}/_info/config", name="api.info.config", methods={"GET"})
     */
    public function config(): JsonResponse
    {
        return $this->json([
            'adminWorker' => [
                'enableAdminWorker' => $this->params->get('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->params->get('shopware.admin_worker.transports'),
            ],
        ]);
    }

    /**
     * @Route("/api/v{version}/_info/events.json", defaults={"auth_required"=false}, name="api.info.events", methods={"GET"})
     */
    public function events(): JsonResponse
    {
        $data = [
            'events' => $this->actionEventRegistry->getEvents(),
        ];

        return $this->json($data);
    }
}
