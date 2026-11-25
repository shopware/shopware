<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Api;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SystemCheckController
{
    /**
     * @param iterable<BaseCheck> $checks
     */
    public function __construct(
        private readonly iterable $checks,
        private readonly SystemCheckResultStorage $resultStorage,
        private readonly SystemChecker $systemChecker,
    ) {
    }

    #[Route(path: '/api/_info/system-check/results', name: 'api.info.system-check.results', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:system_health']], methods: ['GET'])]
    public function list(): JsonResponse
    {
        $results = $this->resultStorage->all();

        $checks = [];
        foreach ($this->checks as $check) {
            $stored = $results[$check->name()] ?? null;

            $checks[] = [
                'name' => $check->name(),
                'category' => $check->category()->name,
                'tags' => $check->tags(),
                'result' => $stored === null ? null : [
                    'status' => $stored->result->status->name,
                    'healthy' => $stored->result->healthy,
                    'message' => $stored->result->message,
                    'extra' => $stored->result->extra,
                    'checkedAt' => $stored->checkedAt->format(\DATE_ATOM),
                ],
            ];
        }

        return new JsonResponse(['checks' => $checks]);
    }

    #[Route(path: '/api/_action/system-check/run', name: 'api.action.system-check.run', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:system_health']], methods: ['POST'])]
    public function run(Request $request): Response
    {
        $this->systemChecker->queueCheck($request->query->has('check') ? $request->query->getString('check') : null);

        return new Response(null, Response::HTTP_ACCEPTED);
    }
}
