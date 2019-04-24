<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SystemConfigController extends AbstractController
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    public function __construct(ConfigurationService $configurationService, SystemConfigService $systemConfig)
    {
        $this->configurationService = $configurationService;
        $this->systemConfig = $systemConfig;
    }

    /**
     * @Route("/api/v{version}/_action/system-config/schema", name="api.action.core.system-config", methods={"GET"})
     *
     * @throws MissingRequestParameterException
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        $domain = $request->query->get('domain');

        if (!$domain) {
            throw new MissingRequestParameterException('domain');
        }

        return new JsonResponse($this->configurationService->getConfiguration($domain));
    }

    /**
     * @Route("/api/v{version}/_action/system-config", name="api.action.core.system-config.value", methods={"GET"})
     */
    public function getConfigurationValues(Request $request): JsonResponse
    {
        $domain = $request->query->get('domain');
        $salesChannelId = $request->query->get('sales_channel_id');

        if (!$domain) {
            throw new MissingRequestParameterException('domain');
        }

        $values = $this->systemConfig->getDomain($domain, $salesChannelId);
        if (empty($values)) {
            $json = '{}';
        } else {
            $json = json_encode($values, JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, 200, [], true);
    }

    /**
     * @Route("/api/v{version}/_action/system-config", name="api.action.core.save.system-config", methods={"POST"})
     */
    public function saveConfiguration(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $kvs = $request->request->all();

        foreach ($kvs as $key => $value) {
            $this->systemConfig->set($key, $value, $salesChannelId);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
