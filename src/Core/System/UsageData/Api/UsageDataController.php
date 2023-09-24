<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Approval\ApprovalDetector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Package('merchant-services')]
#[Route(defaults: ['_routeScope' => ['api']])]
class UsageDataController extends AbstractController
{
    public function __construct(
        private readonly ApprovalDetector $approvalDetector,
    ) {
    }

    #[Route(path: '/api/usage-data/needs-approval', name: 'api.usage-data.request', methods: [Request::METHOD_GET])]
    public function needsApprovalRequest(): JsonResponse
    {
        return new JsonResponse($this->approvalDetector->needsApprovalRequest() && !$this->approvalDetector->isApprovalAlreadyRequested());
    }
}
