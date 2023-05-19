<?php declare(strict_types=1);

namespace Shopware\Core\System\Metrics\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Metrics\Approval\ApprovalDetector;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('merchant-services')]
#[Route(defaults: ['_routeScope' => ['api']])]
class MetricController extends AbstractController
{
    public const SYSTEM_CONFIG_KEY_SHARE_DATA = 'core.metrics.shareUsageData';

    public function __construct(
        private readonly ApprovalDetector $approvalDetector,
        private readonly SystemConfigService $configurationService,
    ) {
    }

    #[Route(path: '/api/metrics/needs-approval', name: 'api.metrics.request', methods: [Request::METHOD_GET])]
    public function needsApprovalRequest(): JsonResponse
    {
        return new JsonResponse($this->approvalDetector->needsApprovalRequest() && !$this->isApprovalAlreadyRequested());
    }

    private function isApprovalAlreadyRequested(): bool
    {
        return $this->configurationService->get(self::SYSTEM_CONFIG_KEY_SHARE_DATA) !== null;
    }
}
