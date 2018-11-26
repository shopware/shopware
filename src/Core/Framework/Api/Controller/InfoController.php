<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InfoController extends AbstractController
{
    /**
     * @var DefinitionService
     */
    private $definitionService;

    public function __construct(DefinitionService $definitionService)
    {
        $this->definitionService = $definitionService;
    }

    /**
     * @Route("/api/_info/v{version}/openapi3.json", name="api.info.openapi3")
     *
     * @throws \Exception
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     */
    public function info()
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/_info/v{version}/entity-schema.json", name="api.info.entity-schema")
     */
    public function entitySchema()
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/_info/v{version}/swagger.html", name="api.info.swagger")
     */
    public function infoHtml(): Response
    {
        return $this->render('@Shopware/swagger.html.twig');
    }
}
