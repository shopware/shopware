<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecks;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class SystemHealthController extends AbstractController
{
    /**
     * @param iterable<BaseCheck> $checks
     */
    public function __construct(
        private readonly iterable $checks,
        private readonly SystemCheckResultStorage $resultStorage,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/api/_admin/system-health', name: 'api.admin.system-health', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:system_health']], methods: ['GET'])]
    public function list(): JsonResponse
    {
        $results = $this->resultStorage->all();

        $checks = [];
        foreach ($this->checks as $check) {
            $checks[] = [
                'name' => $check->name(),
                'category' => $check->category()->name,
                'result' => $results[$check->name()] ?? null,
            ];
        }

        return new JsonResponse(['checks' => $checks]);
    }

    #[Route(path: '/api/_admin/system-health/run', name: 'api.admin.system-health.run', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:system_health']], methods: ['POST'])]
    public function run(Request $request): Response
    {
        $name = $request->query->has('check') ? $request->query->getString('check') : null;

        if ($name !== null && !$this->exists($name)) {
            throw RoutingException::invalidRequestParameter('check');
        }

        $this->messageBus->dispatch(new RunSystemChecks($name));

        return new Response(null, Response::HTTP_ACCEPTED);
    }

    private function exists(string $name): bool
    {
        foreach ($this->checks as $check) {
            if ($check->name() === $name) {
                return true;
            }
        }

        return false;
    }
}
