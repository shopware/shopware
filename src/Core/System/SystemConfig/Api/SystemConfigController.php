<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('system-settings')]
class SystemConfigController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly SystemConfigService $systemConfig,
        private readonly SystemConfigValidator $systemConfigValidator
    ) {
    }

    #[Route(path: '/api/_action/system-config/check', name: 'api.action.core.system-config.check', methods: ['GET'], defaults: ['_acl' => ['system_config:read']])]
    public function checkConfiguration(Request $request, Context $context): JsonResponse
    {
        $domain = (string) $request->query->get('domain');

        if ($domain === '') {
            return new JsonResponse(false);
        }

        return new JsonResponse($this->configurationService->checkConfiguration($domain, $context));
    }

    #[Route(path: '/api/_action/system-config/schema', name: 'api.action.core.system-config', methods: ['GET'])]
    public function getConfiguration(Request $request, Context $context): JsonResponse
    {
        $domain = (string) $request->query->get('domain');

        if ($domain === '') {
            throw new MissingRequestParameterException('domain');
        }

        return new JsonResponse($this->configurationService->getConfiguration($domain, $context));
    }

    #[Route(path: '/api/_action/system-config', name: 'api.action.core.system-config.value', methods: ['GET'], defaults: ['_acl' => ['system_config:read']])]
    public function getConfigurationValues(Request $request): JsonResponse
    {
        $domain = (string) $request->query->get('domain');
        if ($domain === '') {
            throw new MissingRequestParameterException('domain');
        }

        $salesChannelId = $request->query->get('salesChannelId');
        if (!\is_string($salesChannelId)) {
            $salesChannelId = null;
        }

        $inherit = $request->query->getBoolean('inherit');

        $values = $this->systemConfig->getDomain($domain, $salesChannelId, $inherit);
        if (empty($values)) {
            $json = '{}';
        } else {
            $json = json_encode($values, \JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, 200, [], true);
    }

    #[Route(path: '/api/_action/system-config', name: 'api.action.core.save.system-config', methods: ['POST'], defaults: ['_acl' => ['system_config:update', 'system_config:create', 'system_config:delete']])]
    public function saveConfiguration(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        if (!\is_string($salesChannelId)) {
            $salesChannelId = null;
        }

        $kvs = $request->request->all();
        $this->saveKeyValues($salesChannelId, $kvs);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @deprecated tag:v6.6.0 $context param will be required
     */
    #[Route(path: '/api/_action/system-config/batch', name: 'api.action.core.save.system-config.batch', methods: ['POST'], defaults: ['_acl' => ['system_config:update', 'system_config:create', 'system_config:delete']])]
    public function batchSaveConfiguration(Request $request, ?Context $context = null): JsonResponse
    {
        if (!$context) {
            Feature::triggerDeprecationOrThrow(
                'v6.6.0.0',
                'Second parameter `$context` will be required in method `batchSaveConfiguration()` in `SystemConfigController` in v6.6.0.0'
            );

            $context = Context::createDefaultContext();
        }

        $this->systemConfigValidator->validate($request->request->all(), $context);

        /**
         * @var string $salesChannelId
         * @var array<string, mixed> $kvs
         */
        foreach ($request->request->all() as $salesChannelId => $kvs) {
            if ($salesChannelId === 'null') {
                $salesChannelId = null;
            }
            $this->saveKeyValues($salesChannelId, $kvs);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $kvs
     */
    private function saveKeyValues(?string $salesChannelId, array $kvs): void
    {
        foreach ($kvs as $key => $value) {
            $this->systemConfig->set($key, $value, $salesChannelId);
        }
    }
}
