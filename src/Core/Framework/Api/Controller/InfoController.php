<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Event\ActionEventRegistry;
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
     * @var ActionEventRegistry
     */
    private $actionEventRegistry;

    public function __construct(DefinitionService $definitionService, ParameterBagInterface $params, ActionEventRegistry $actionEventRegistry)
    {
        $this->definitionService = $definitionService;
        $this->params = $params;
        $this->actionEventRegistry = $actionEventRegistry;
    }

    /**
     * @Route("/api/v{version}/_info/openapi3.json", name="api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     *
     * @return JsonResponse|Response
     */
    public function info()
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/entity-schema.json", name="api.info.entity-schema", methods={"GET"})
     */
    public function entitySchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/swagger.html", name="api.info.swagger", methods={"GET"})
     */
    public function infoHtml(): Response
    {
        return $this->render('@Shopware/swagger.html.twig');
    }

    /**
     * @Route("/api/v{version}/_info/config", name="api.info.config", methods={"GET"})
     */
    public function config(): JsonResponse
    {
        return $this->json([
            'adminWorker' => [
                'enableAdminWorker' => $this->params->get('shopware.admin_worker.enable_admin_worker'),
                'pollInterval' => $this->params->get('shopware.admin_worker.poll_interval'),
            ],
        ]);
    }

    /**
     * @Route("/api/v{version}/_info/events.json", name="api.info.events", methods={"GET"})
     */
    public function events(): JsonResponse
    {
        $data = [
            'events' => $this->actionEventRegistry->getEvents(),
        ];

        return $this->json($data);
    }
}
