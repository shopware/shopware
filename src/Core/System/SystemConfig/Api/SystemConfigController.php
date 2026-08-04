<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SystemConfigController extends AbstractController
{
    /**
     * @internal
     *
     * @deprecated tag:v6.8.0 - $configurationService will be removed
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly SystemConfigDefinitionService $systemConfigDefinitionService,
        private readonly SystemConfigService $systemConfig,
        private readonly SystemConfigValidator $systemConfigValidator
    ) {
    }

    #[Route(
        path: '/api/_action/system-config/check',
        name: 'api.action.core.system-config.check',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function checkConfiguration(Request $request, Context $context): JsonResponse
    {
        $domain = $request->query->getString('domain');

        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        return new JsonResponse($this->systemConfigDefinitionService->checkConfiguration($domain, $context));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use {@see getSchema} instead.
     */
    #[Route(
        path: '/api/_action/system-config/schema',
        name: 'api.action.core.system-config',
        methods: [Request::METHOD_GET]
    )]
    public function getConfiguration(Request $request, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            'Route "/api/_action/system-config/schema" is deprecated and will be removed in v6.8.0.0. Use "/api/_action/system-config/get-schema" instead.',
        );

        $domain = $request->query->getString('domain');

        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        return Feature::silent('v6.8.0.0', fn () => new JsonResponse($this->configurationService->getConfiguration($domain, $context)));
    }

    #[Route(
        path: '/api/_action/system-config/get-schema',
        name: 'api.action.core.system-config.get-schema',
        methods: [Request::METHOD_GET]
    )]
    public function getSchema(Request $request, Context $context): JsonResponse
    {
        $domain = $request->query->getString('domain');

        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        return new JsonResponse($this->systemConfigDefinitionService->getConfiguration($domain, $context));
    }

    #[Route(
        path: '/api/_action/system-config',
        name: 'api.action.core.system-config.value',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getConfigurationValues(Request $request): JsonResponse
    {
        $domain = $request->query->getString('domain');

        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        $salesChannelId = $request->query->get('salesChannelId');

        if (!\is_string($salesChannelId)) {
            $salesChannelId = null;
        }

        $inherit = $request->query->getBoolean('inherit');
        $values = $this->systemConfig->getDomain($domain, $salesChannelId, $inherit);

        if ($values === []) {
            $json = '{}';
        } else {
            $json = json_encode($values, \JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route(
        path: '/api/_action/system-config',
        name: 'api.action.core.save.system-config',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:update', 'system_config:create', 'system_config:delete']],
        methods: [Request::METHOD_POST]
    )]
    public function saveConfiguration(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');

        if (!\is_string($salesChannelId)) {
            $salesChannelId = null;
        }

        $kvs = $request->request->all();

        // Keep omitted ?silent aligned with the feature-flagged SystemConfigService default during the 6.7/6.8 transition.
        // @deprecated tag:v6.8.0 - remove the legacy branch and keep the feature-active path.
        if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
            $this->systemConfig->setMultiple($kvs, $salesChannelId, $request->query->getBoolean('silent', true));
        } elseif ($request->query->has('silent')) {
            $this->systemConfig->setMultiple($kvs, $salesChannelId, $request->query->getBoolean('silent'));
        } else {
            $this->systemConfig->setMultiple($kvs, $salesChannelId);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/system-config/batch',
        name: 'api.action.core.save.system-config.batch',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:update', 'system_config:create', 'system_config:delete']],
        methods: [Request::METHOD_POST]
    )]
    public function batchSaveConfiguration(Request $request, Context $context): JsonResponse
    {
        $this->systemConfigValidator->validate($request->request->all(), $context);

        /**
         * @var string $salesChannelId
         * @var array<string, mixed> $kvs
         */
        foreach ($request->request->all() as $salesChannelId => $kvs) {
            if ($salesChannelId === 'null') {
                $salesChannelId = null;
            }

            // Keep omitted ?silent aligned with the feature-flagged SystemConfigService default during the 6.7/6.8 transition.
            // @deprecated tag:v6.8.0 - remove the legacy branch and keep the feature-active path.
            if (Feature::isActive('v6.8.0.0') || Feature::isActive('CACHE_REWORK')) {
                $this->systemConfig->setMultiple($kvs, $salesChannelId, $request->query->getBoolean('silent', true));
            } elseif ($request->query->has('silent')) {
                $this->systemConfig->setMultiple($kvs, $salesChannelId, $request->query->getBoolean('silent'));
            } else {
                $this->systemConfig->setMultiple($kvs, $salesChannelId);
            }
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
