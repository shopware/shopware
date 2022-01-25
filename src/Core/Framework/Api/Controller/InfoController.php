<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class InfoController extends AbstractController
{
    private DefinitionService $definitionService;

    private ParameterBagInterface $params;

    private Packages $packages;

    private Kernel $kernel;

    private bool $enableUrlFeature;

    private array $cspTemplates;

    private BusinessEventCollector $eventCollector;

    private ?FlowActionCollector $flowActionCollector;

    private IncrementGatewayRegistry $incrementGatewayRegistry;

    private EntityRepositoryInterface $appRepository;

    public function __construct(
        DefinitionService $definitionService,
        ParameterBagInterface $params,
        Kernel $kernel,
        Packages $packages,
        BusinessEventCollector $eventCollector,
        IncrementGatewayRegistry $incrementGatewayRegistry,
        EntityRepositoryInterface $appRepository,
        ?FlowActionCollector $flowActionCollector = null,
        bool $enableUrlFeature = true,
        array $cspTemplates = []
    ) {
        $this->definitionService = $definitionService;
        $this->params = $params;
        $this->packages = $packages;
        $this->kernel = $kernel;
        $this->enableUrlFeature = $enableUrlFeature;
        $this->flowActionCollector = $flowActionCollector;
        $this->cspTemplates = $cspTemplates;
        $this->eventCollector = $eventCollector;
        $this->incrementGatewayRegistry = $incrementGatewayRegistry;
        $this->appRepository = $appRepository;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Get(
     *     path="/_info/openapi3.json",
     *     summary="Get OpenAPI Specification",
     *     description="Get information about the API in OpenAPI format.",
     *     operationId="api-info",
     *     tags={"Admin API", "System Info & Healthcheck"},
     *     @OA\Parameter(
     *         name="type",
     *         description="Type of the api",
     *         @OA\Schema(type="string", enum={"jsonapi", "json"}),
     *         in="query"
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Returns information about the API."
     *     )
     * )
     * @Route("/api/_info/openapi3.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="api.info.openapi3", methods={"GET"})
     *
     * @throws \Exception
     */
    public function info(Request $request): JsonResponse
    {
        $apiType = $request->query->getAlpha('type', DefinitionService::TypeJsonApi);
        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::API, $apiType);

        return new JsonResponse($data);
    }

    /**
     * @Since("6.4.6.0")
     * @Route("/api/_info/queue.json", name="api.info.queue", methods={"GET"})
     *
     * @throws \Exception
     */
    public function queue(): JsonResponse
    {
        try {
            $gateway = $this->incrementGatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        } catch (IncrementGatewayNotFoundException $exception) {
            // In case message_queue pool is disabled
            return new JsonResponse([]);
        }

        // Fetch unlimited message_queue_stats
        $entries = $gateway->list('message_queue_stats', -1);

        return new JsonResponse(array_map(function (array $entry) {
            return [
                'name' => $entry['key'],
                'size' => (int) $entry['count'],
            ];
        }, array_values($entries)));
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_info/open-api-schema.json", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="api.info.open-api-schema", methods={"GET"})
     */
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::API);

        return new JsonResponse($data);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_info/entity-schema.json", name="api.info.entity-schema", methods={"GET"})
     */
    public function entitySchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(EntitySchemaGenerator::FORMAT, DefinitionService::API);

        return new JsonResponse($data);
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Get(
     *     path="/_info/events.json",
     *     summary="Get Business events",
     *     description="Get a list of about the business events.",
     *     operationId="business-events",
     *     tags={"Admin API", "System Info & Healthcheck"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns a list of about the business events.",
     *         @OA\JsonContent(ref="#/components/schemas/businessEventsResponse")
     *     )
     * )
     * @Route("/api/_info/events.json", name="api.info.business-events", methods={"GET"})
     */
    public function businessEvents(Context $context): JsonResponse
    {
        $events = $this->eventCollector->collect($context);

        return $this->json($events);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_info/swagger.html", defaults={"auth_required"="%shopware.api.api_browser.auth_required_str%"}, name="api.info.swagger", methods={"GET"})
     */
    public function infoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TypeJson);
        $response = $this->render(
            '@Framework/swagger.html.twig',
            [
                'schemaUrl' => 'api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        );

        $cspTemplate = $this->cspTemplates['administration'] ?? '';
        $cspTemplate = trim($cspTemplate);
        if ($cspTemplate !== '') {
            $csp = str_replace('%nonce%', $nonce, $cspTemplate);
            $csp = str_replace(["\n", "\r"], ' ', $csp);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    /**
     * @Since("6.0.0.0")
     * @OA\Get(
     *     path="/_info/config",
     *     summary="Get API information",
     *     description="Get information about the API",
     *     operationId="config",
     *     tags={"Admin API", "System Info & Healthcheck"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns information about the API.",
     *         @OA\JsonContent(ref="#/components/schemas/infoConfigResponse")
     *     )
     * )
     * @Route("/api/_info/config", name="api.info.config", methods={"GET"})
     *
     * @deprecated tag:v6.5.0 $context param will be required
     */
    public function config(?Context $context = null): JsonResponse
    {
        if (!$context) {
            $context = Context::createDefaultContext();
        }

        return new JsonResponse([
            'version' => $this->params->get('kernel.shopware_version'),
            'versionRevision' => $this->params->get('kernel.shopware_version_revision'),
            'adminWorker' => [
                'enableAdminWorker' => $this->params->get('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->params->get('shopware.admin_worker.transports'),
            ],
            'bundles' => $this->getBundles($context),
            'settings' => [
                'enableUrlFeature' => $this->enableUrlFeature,
            ],
        ]);
    }

    /**
     * @Since("6.3.5.0")
     * @OA\Get(
     *     path="/_info/version",
     *     summary="Get the Shopware version",
     *     description="Get the version of the Shopware instance",
     *     operationId="infoShopwareVersion",
     *     tags={"Admin API", "System Info & Healthcheck"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns the version of the Shopware instance.",
     *         @OA\JsonContent(
     *              @OA\Property(
     *                  property="version",
     *                  description="The Shopware version.",
     *                  type="string"
     *              )
     *          )
     *     )
     * )
     * @Route("/api/_info/version", name="api.info.shopware.version", methods={"GET"})
     * @Route("/api/v1/_info/version", name="api.info.shopware.version_old_version", methods={"GET"})
     */
    public function infoShopwareVersion(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->params->get('kernel.shopware_version'),
        ]);
    }

    /**
     * @Since("6.4.5.0")
     * @OA\Get(
     *     path="/_info/flow-actions.json",
     *     summary="Get actions for flow builder",
     *     description="Get a list of action for flow builder.",
     *     operationId="flow-actions",
     *     tags={"Admin API", "System Info & Healthcheck"},
     *     @OA\Response(
     *         response="200",
     *         description="Returns a list of action for flow builder.",
     *         @OA\JsonContent(ref="#/components/schemas/flowBulderActionsResponse")
     *     )
     * )
     * @Route("/api/_info/flow-actions.json", name="api.info.actions", methods={"GET"})
     */
    public function flowActions(Context $context): JsonResponse
    {
        if (!$this->flowActionCollector) {
            return $this->json([]);
        }

        $events = $this->flowActionCollector->collect($context);

        return $this->json($events);
    }

    private function getBundles(Context $context): array
    {
        $assets = [];
        $package = $this->packages->getPackage('asset');

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundleDirectoryName = preg_replace('/bundle$/', '', mb_strtolower($bundle->getName()));
            if ($bundleDirectoryName === null) {
                throw new \RuntimeException(sprintf('Unable to generate bundle directory for bundle "%s"', $bundle->getName()));
            }

            $styles = array_map(static function (string $filename) use ($package, $bundleDirectoryName) {
                $url = 'bundles/' . $bundleDirectoryName . '/' . $filename;

                return $package->getUrl($url);
            }, $this->getAdministrationStyles($bundle));

            $scripts = array_map(static function (string $filename) use ($package, $bundleDirectoryName) {
                $url = 'bundles/' . $bundleDirectoryName . '/' . $filename;

                return $package->getUrl($url);
            }, $this->getAdministrationScripts($bundle));

            $baseUrl = $this->getBaseUrl($bundle, $package, $bundleDirectoryName);

            if (empty($styles) && empty($scripts)) {
                if (!Feature::isActive('FEATURE_NEXT_17950') || $baseUrl === null) {
                    continue;
                }
            }

            $assets[$bundle->getName()] = [
                'css' => $styles,
                'js' => $scripts,
            ];

            if (Feature::isActive('FEATURE_NEXT_17950')) {
                $assets[$bundle->getName()]['baseUrl'] = $baseUrl;
                $assets[$bundle->getName()]['type'] = 'plugin';
            }
        }

        if (!Feature::isActive('FEATURE_NEXT_17950')) {
            return $assets;
        }

        foreach ($this->getActiveApps($context) as $app) {
            $assets[$app->getName()] = [
                'type' => 'app',
                'baseUrl' => $app->getBaseAppUrl(),
                'permissions' => $this->fetchAppPermissions($app),
                'version' => $app->getVersion(),
            ];
        }

        return $assets;
    }

    private function fetchAppPermissions(AppEntity $app): array
    {
        $privileges = [];
        $aclRole = $app->getAclRole();
        if ($aclRole === null) {
            return $privileges;
        }

        foreach ($aclRole->getPrivileges() as $privilege) {
            [ $entity, $key ] = \explode(':', $privilege);
            $privileges[$key][] = $entity;
        }

        return $privileges;
    }

    private function getAdministrationStyles(Bundle $bundle): array
    {
        $path = 'administration/css/' . str_replace('_', '-', $bundle->getContainerPrefix()) . '.css';
        $bundlePath = $bundle->getPath();

        if (!file_exists($bundlePath . '/Resources/public/' . $path)) {
            return [];
        }

        return [$path];
    }

    private function getAdministrationScripts(Bundle $bundle): array
    {
        $path = 'administration/js/' . str_replace('_', '-', $bundle->getContainerPrefix()) . '.js';
        $bundlePath = $bundle->getPath();

        if (!file_exists($bundlePath . '/Resources/public/' . $path)) {
            return [];
        }

        return [$path];
    }

    private function getBaseUrl(Bundle $bundle, PackageInterface $package, string $bundleDirectoryName): ?string
    {
        if (!$bundle instanceof Plugin) {
            return null;
        }

        if ($bundle->getAdminBaseUrl()) {
            return $bundle->getAdminBaseUrl();
        }

        $defaultEntryFile = 'administration/index.html';
        $bundlePath = $bundle->getPath();

        if (!file_exists($bundlePath . '/Resources/public/' . $defaultEntryFile)) {
            return null;
        }

        $url = 'bundles/' . $bundleDirectoryName . '/' . $defaultEntryFile;

        return $package->getUrl($url);
    }

    private function getActiveApps(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('aclRole');
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('active', true),
                    new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('baseAppUrl', null)]),
                ]
            )
        );

        /** @var AppCollection $apps */
        $apps = $this->appRepository->search(new Criteria(), $context)->getEntities();

        return $apps;
    }
}
