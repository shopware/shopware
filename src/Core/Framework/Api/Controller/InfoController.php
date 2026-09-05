<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Content\Media\Upload\PresignedMediaUploadService;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\Api\Route\RouteInfo;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\ContentSystem\Adapter\NoneSpecificationSource;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredSchemaResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type StyleOptionSchema from StyleOptionSpecification
 * @phpstan-import-type BindingSpecificationSchema from BindingSpecification
 * @phpstan-import-type StoredSchemaEntry from StoredSchemaResolver
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class InfoController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionService $definitionService,
        private readonly ParameterBagInterface $params,
        private readonly BusinessEventCollector $eventCollector,
        private readonly IncrementGatewayRegistry $incrementGatewayRegistry,
        private readonly MigrationInfo $migrationInfo,
        private readonly AppUrlVerifier $appUrlVerifier,
        private readonly FlowActionCollector $flowActionCollector,
        private readonly SystemConfigService $systemConfigService,
        private readonly ApiRouteInfoResolver $apiRouteInfoResolver,
        private readonly InAppPurchase $inAppPurchase,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly StatsService $messageStatsService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContentSystemDataLoaderSchemaGenerator $dataLoaderSchemaGenerator,
        private readonly AbstractContentSystemElementTypeRegistry $elementTypeRegistry,
        private readonly AbstractContentSystemStyleOptionRegistry $styleOptionRegistry,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingSpecificationRegistry,
        private readonly StoredSchemaResolver $storedSchemaResolver,
        private readonly ?PresignedMediaUploadService $presignedMediaUploadService,
        private readonly MediaFileExtensionListProvider $mediaFileExtensionListProvider,
    ) {
    }

    #[Route(
        path: '/api/_info/openapi3.json',
        name: 'api.info.openapi3',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function info(Request $request): JsonResponse
    {
        $type = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);

        $apiType = $this->definitionService->toApiType($type);
        if ($apiType === null) {
            throw ApiException::invalidApiType($type);
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::API, $apiType);

        return new JsonResponse($data);
    }

    /**
     * @deprecated tag:v6.8.0 - Route will be removed. Use /api/_info/message-stats.json instead.
     */
    #[Route(path: '/api/_info/queue.json', name: 'api.info.queue', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['message_queue_stats:read']], methods: ['GET'])]
    public function queue(): JsonResponse
    {
        if (Feature::isActive('v6.8.0.0')) { // avoiding polluting logs, as our code still calling this endpoint
            Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '\Shopware\Core\Framework\Api\Controller\InfoController::messageStats'));
        }

        try {
            $gateway = $this->incrementGatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        } catch (IncrementGatewayNotFoundException) {
            // In case message_queue pool is disabled
            return new JsonResponse([]);
        }

        // Fetch unlimited message_queue_stats
        $entries = $gateway->list('message_queue_stats', -1);

        return new JsonResponse(array_map(static fn (array $entry) => [
            'name' => $entry['key'],
            'size' => $entry['count'],
        ], array_values($entries)));
    }

    #[Route(path: '/api/_info/message-stats.json', name: 'api.info.message-stats', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['message_queue_stats:read']], methods: ['GET'])]
    public function messageStats(): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($this->messageStatsService->getStats());

        return $response;
    }

    #[Route(
        path: '/api/_info/open-api-schema.json',
        name: 'api.info.open-api-schema',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        return new JsonResponse($data);
    }

    #[Route(path: '/api/_info/entity-schema.json', name: 'api.info.entity-schema', methods: ['GET'])]
    public function entitySchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(EntitySchemaGenerator::FORMAT);

        return new JsonResponse($data);
    }

    #[Route(path: '/api/_info/content-system-data-loaders.json', name: 'api.info.content-system-data-loaders', methods: ['GET'])]
    public function contentSystemDataLoaders(): JsonResponse
    {
        return new JsonResponse($this->dataLoaderSchemaGenerator->getSchema());
    }

    #[Route(path: '/api/_info/content-system-entity-types.json', name: 'api.info.content-system-entity-types', methods: ['GET'])]
    public function contentSystemEntityTypes(): JsonResponse
    {
        return new JsonResponse(['entityTypes' => $this->rootSourceRegistry->entityRootSources()]);
    }

    #[Route(path: '/api/_info/content-system-root-sources.json', name: 'api.info.content-system-root-sources', methods: ['GET'])]
    public function contentSystemRootSources(Context $context): JsonResponse
    {
        $rootSources = array_map(
            fn (string $rootSource): array => [
                'id' => $rootSource,
                'kind' => $this->contentSystemRootSourceKind($rootSource),
                'providedContext' => array_map(
                    static fn (ProvidedContext $provided): array => [
                        'contextKey' => $provided->contextKey,
                        'fqcn' => $provided->fqcn,
                        'contextType' => $provided->contextType->value,
                        'distribution' => $provided->distribution->value,
                    ],
                    $this->rootSourceRegistry->resolve($rootSource, $context)
                ),
            ],
            $this->rootSourceRegistry->knownRootSources()
        );

        return new JsonResponse(['rootSources' => $rootSources]);
    }

    #[Route(path: '/api/_info/events.json', name: 'api.info.business-events', methods: ['GET'])]
    public function businessEvents(Context $context): JsonResponse
    {
        $events = $this->eventCollector->collect($context);

        return new JsonResponse($events);
    }

    #[Route(
        path: '/api/_info/stoplightio.html',
        name: 'api.info.stoplightio',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function stoplightIoInfoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON);
        $response = $this->render(
            '@Framework/stoplightio.html.twig',
            [
                'schemaUrl' => 'api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        );

        $cspTemplate = trim($this->params->get('shopware.security.csp_templates')['administration'] ?? '');
        if ($cspTemplate !== '') {
            $csp = str_replace(['%nonce%', "\n", "\r"], [$nonce, ' ', ' '], $cspTemplate);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(path: '/api/_info/config', name: 'api.info.config', methods: ['GET'])]
    public function config(Context $context, Request $request): JsonResponse
    {
        $adminWorker = [
            'enableAdminWorker' => $this->params->get('shopware.admin_worker.enable_admin_worker'),
            'enableNotificationWorker' => $this->params->get('shopware.admin_worker.enable_notification_worker'),
            'transports' => $this->getAdminWorkerTransports(),
        ];

        if (!Feature::isActive('v6.8.0.0')) {
            $adminWorker['enableQueueStatsWorker'] = $this->params->get('shopware.admin_worker.enable_queue_stats_worker');
        }

        $config = [
            'version' => $this->getShopwareVersion(),
            'shopId' => $this->getShopId(),
            'appUrl' => (string) EnvironmentHelper::getVariable('APP_URL'),
            'versionRevision' => $this->params->get('kernel.shopware_version_revision'),
            'adminWorker' => $adminWorker,
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => $this->params->get('shopware.media.enable_url_upload_feature'),
                'presignedUploadSupported' => $this->presignedMediaUploadService !== null
                    && $this->presignedMediaUploadService->isAvailable(),
                'appUrlReachable' => $this->appUrlVerifier->isAppUrlReachable($request),
                'appsRequireAppUrl' => $this->appUrlVerifier->hasAppsThatNeedAppUrl(),
                'firstMigrationDate' => $this->migrationInfo->getFirstMigrationDate(),
                'private_allowed_extensions' => $this->mediaFileExtensionListProvider->getAllowedExtensions(true, $context),
                'private_allowed_mime_types_by_extension' => $this->mediaFileExtensionListProvider->getMimeTypesByExtension(true, $context),
                'enableHtmlSanitizer' => $this->params->get('shopware.html_sanitizer.enabled'),
                'enableStagingMode' => $this->params->get('shopware.staging.administration.show_banner') && $this->systemConfigService->getBool(SetupStagingEvent::CONFIG_FLAG),
                'disableExtensionManagement' => !$this->params->get('shopware.deployment.runtime_extension_management'),
                'minSearchTermLength' => $this->systemConfigService->getInt('core.search.minSearchTermLength') ?: 2,
            ],
            'inAppPurchases' => $this->inAppPurchase->all(),
        ];

        $config = $this->eventDispatcher->dispatch(new AdminInfoConfigEvent($config))->getConfig();

        return new JsonResponse($config);
    }

    #[Route(path: '/api/_info/version', name: 'api.info.shopware.version', methods: ['GET'])]
    #[Route(path: '/api/v1/_info/version', name: 'api.info.shopware.version_old_version', methods: ['GET'])]
    public function infoShopwareVersion(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->getShopwareVersion(),
        ]);
    }

    #[Route(path: '/api/_info/flow-actions.json', name: 'api.info.actions', methods: ['GET'])]
    public function flowActions(Context $context): JsonResponse
    {
        return new JsonResponse($this->flowActionCollector->collect($context));
    }

    #[Route(
        path: '/api/_info/routes',
        name: 'api.info.routes',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function getRoutes(): JsonResponse
    {
        $endpoints = array_map(
            static fn (RouteInfo $endpoint) => ['path' => $endpoint->path, 'methods' => $endpoint->methods],
            $this->apiRouteInfoResolver->getApiRoutes(ApiRouteScope::ID)
        );

        return new JsonResponse(['endpoints' => $endpoints]);
    }

    #[Route(path: '/api/_info/content-system-element-types.json', name: 'api.info.content-system-element-types', methods: ['GET'])]
    public function getContentSystemElementTypes(): JsonResponse
    {
        $types = array_map(
            fn (ContentSystemElementTypeSpecification $def) => $this->elementTypeSchema($def),
            array_values($this->elementTypeRegistry->all())
        );

        // styleOptions are universal (settable on every type), so they are folded in here as well as served standalone.
        // Cast to an object so an empty option set serializes as {} (the OpenAPI type: object), not [].
        return new JsonResponse(['types' => $types, 'styleOptions' => (object) $this->styleOptionSchemas()]);
    }

    #[Route(path: '/api/_info/content-system-style-options.json', name: 'api.info.content-system-style-options', methods: ['GET'])]
    public function getContentSystemStyleOptions(): JsonResponse
    {
        // Cast to an object so an empty option set serializes as {} (the OpenAPI type: object), not [].
        return new JsonResponse(['styleOptions' => (object) $this->styleOptionSchemas()]);
    }

    /**
     * bindingSpecifications are folded into each type entry (mirrors the styleOptions precedent), keyed by
     * source-qualified id. Cast to an object so a type with none serializes {} (the OpenAPI type: object), not [].
     *
     * storageSchema is folded in the same way, keyed by stored key: what an element of this type stores, as
     * opposed to the spec's own properties, which is the hydrated output schema. Cast to an object so a type
     * that stores nothing serializes {} (the OpenAPI type: object), not [].
     *
     * @return array<string, mixed> the type's ElementTypeSchema plus the folded bindingSpecifications and
     *                              storageSchema objects
     */
    private function elementTypeSchema(ContentSystemElementTypeSpecification $def): array
    {
        $schema = $def->toSchema();
        $schema['bindingSpecifications'] = (object) $this->bindingSpecificationSchemasForType($def->name());
        $schema['storageSchema'] = (object) $this->storedSchemaResolver->resolve($def);

        return $schema;
    }

    /**
     * @return array<string, StyleOptionSchema> the registered style options keyed by their wire name
     */
    private function styleOptionSchemas(): array
    {
        return array_map(
            static fn (StyleOptionSpecification $spec) => $spec->toSchema(),
            $this->styleOptionRegistry->allResolved()
        );
    }

    /**
     * @return array<string, BindingSpecificationSchema> keyed by qualified id ("source:id"), filtered to the given type
     */
    private function bindingSpecificationSchemasForType(string $type): array
    {
        $schemas = [];

        foreach ($this->bindingSpecificationRegistry->byType($type) as $specification) {
            $schemas[$specification->qualifiedId()] = $specification->toSchema();
        }

        return $schemas;
    }

    /**
     * "none" for the NoneSpecificationSource id, "entity" for an entity-type root source, "section" for the rest
     * (the section keys header and footer).
     */
    private function contentSystemRootSourceKind(string $rootSource): string
    {
        if ($rootSource === NoneSpecificationSource::ROOT_SOURCE) {
            return 'none';
        }

        if (\in_array($rootSource, $this->rootSourceRegistry->entityRootSources(), true)) {
            return 'entity';
        }

        return 'section';
    }

    /**
     * @return list<string>
     */
    private function getAdminWorkerTransports(): array
    {
        $transports = $this->params->get('shopware.admin_worker.transports');
        if (!\is_array($transports)) {
            return [];
        }

        /** @var list<string> $transports */
        $transports = array_values($transports);

        if (Feature::isActive('WEBHOOKS_REWORK')) {
            return $transports;
        }

        return array_values(array_filter($transports, static fn (string $transport): bool => $transport !== 'webhook'));
    }

    private function getShopwareVersion(): string
    {
        $shopwareVersion = $this->params->get('kernel.shopware_version');
        if ($shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            $shopwareVersion = str_replace('.9999999-dev', '.9999999.9999999-dev', $shopwareVersion);
        }

        return $shopwareVersion;
    }

    private function getShopId(): string
    {
        try {
            return $this->shopIdProvider->getShopId()->id;
        } catch (ShopIdChangeSuggestedException $e) {
            return $e->shopId->id;
        }
    }
}
