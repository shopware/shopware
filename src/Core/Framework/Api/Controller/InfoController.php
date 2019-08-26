<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\Exception\ApiBrowserNotPublicException;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
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

    /**
     * @var bool
     */
    private $isApiBrowserPublic;

    /**
     * @var Packages
     */
    private $packages;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(
        DefinitionService $definitionService,
        ParameterBagInterface $params,
        BusinessEventRegistry $actionEventRegistry,
        bool $isApiBrowserPublic,
        Kernel $kernel,
        Packages $packages
    ) {
        $this->definitionService = $definitionService;
        $this->params = $params;
        $this->actionEventRegistry = $actionEventRegistry;
        $this->isApiBrowserPublic = $isApiBrowserPublic;
        $this->packages = $packages;
        $this->kernel = $kernel;
    }

    /**
     * @Route("/api/v{version}/_info/openapi3.json", defaults={"auth_required"=false}, name="api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     */
    public function info(): JsonResponse
    {
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT);

        return $this->json($data);
    }

    /**
     * @Route("/api/v{version}/_info/open-api-schema.json", defaults={"auth_required"=false}, name="api.info.open-api-schema", methods={"GET"})
     */
    public function openApiSchema(): JsonResponse
    {
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

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
        if (!$this->isApiBrowserPublic) {
            throw new ApiBrowserNotPublicException();
        }

        return $this->render('@Framework/swagger.html.twig', ['schemaUrl' => 'api.info.openapi3']);
    }

    /**
     * @Route("/api/v{version}/_info/config", name="api.info.config", methods={"GET"})
     */
    public function config(): JsonResponse
    {
        return $this->json([
            'version' => $this->params->get('kernel.shopware_version'),
            'versionRevision' => $this->params->get('kernel.shopware_version_revision'),
            'adminWorker' => [
                'enableAdminWorker' => $this->params->get('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->params->get('shopware.admin_worker.transports'),
            ],
            'bundles' => $this->getBundles(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_info/business-events.json", defaults={"auth_required"=false}, name="api.info.events", methods={"GET"})
     */
    public function events(): JsonResponse
    {
        $data = [
            'events' => $this->actionEventRegistry->getEvents(),
        ];

        return $this->json($data);
    }

    private function getBundles(): array
    {
        $assets = [];
        $package = $this->packages->getPackage();

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundleName = strtolower($bundle->getName());

            $styles = array_map(static function (string $filename) use ($package, $bundleName) {
                $url = sprintf('bundles/%s/administration/css/%s', $bundleName, $filename);

                return $package->getUrl($url);
            }, $bundle->getAdministrationStyles());

            $scripts = array_map(static function (string $filename) use ($package, $bundleName) {
                $url = sprintf('bundles/%s/administration/js/%s', $bundleName, $filename);

                return $package->getUrl($url);
            }, $bundle->getAdministrationScripts());

            if (empty($styles) && empty($scripts)) {
                continue;
            }

            $assets[$bundle->getName()] = [
                'css' => $styles,
                'js' => $scripts,
            ];
        }

        return $assets;
    }
}
