<?php declare(strict_types=1);

namespace Shopware\Rest\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Rest\ApiDefinition\DefinitionService;
use Shopware\Rest\ApiDefinition\Generator\OpenApi3Generator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class InfoController extends Controller
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
     * @Route("/api/info.{format}", name="api_info")
     */
    public function infoYamlAction(string $format)
    {
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT);

        switch ($format) {
            case 'yaml':
                return new Response(Yaml::dump($data, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE), 200);
                break;
            case 'json':
                return $this->json($data);
                break;
            default:
        }

        throw new \Exception('Unsupported extension');
    }

    /**
     * @Route("/api/entity-schema.{format}", name="api_entity_schema")
     */
    public function entitySchemaAction(string $format)
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        switch ($format) {
            case 'yaml':
                return new Response(Yaml::dump($data, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE), 200);
                break;
            case 'json':
                return $this->json($data);
                break;
            default:
        }

        throw new \Exception('Unsupported extension');
    }

    /**
     * @Route("/api/info", name="api_info_html")
     */
    public function infoHtmlAction()
    {
        return $this->render('@Rest/swagger.html.twig');
    }
}