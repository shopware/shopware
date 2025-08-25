<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment\Controller;

use Shopware\Core\Framework\Increment\IncrementException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class IncrementApiController
{
    /**
     * @internal
     */
    public function __construct(private readonly IncrementGatewayRegistry $gatewayRegistry)
    {
    }

    #[Route(path: '/api/_action/increment/{pool}', name: 'api.increment.increment', methods: ['POST'])]
    public function increment(Request $request, string $pool): Response
    {
        $key = $request->request->get('key');

        if (!$key || !\is_string($key)) {
            throw IncrementException::keyParameterIsMissing();
        }

        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $poolGateway->increment($cluster, $key);

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/api/_action/decrement/{pool}', name: 'api.increment.decrement', methods: ['POST'])]
    public function decrement(Request $request, string $pool): Response
    {
        $key = $request->request->get('key');

        if (!$key || !\is_string($key)) {
            throw IncrementException::keyParameterIsMissing();
        }

        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $poolGateway->decrement(
            $cluster,
            $key
        );

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/api/_action/increment/{pool}', name: 'api.increment.list', methods: ['GET'])]
    public function getIncrement(string $pool, Request $request): Response
    {
        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $limit = $request->query->getInt('limit', 5);
        $offset = $request->query->getInt('offset', 0);

        $result = $poolGateway->list($cluster, $limit, $offset);

        return new JsonResponse($result);
    }

    #[Route(path: '/api/_action/reset-increment/{pool}', name: 'api.increment.reset', methods: ['POST'])]
    public function reset(string $pool, Request $request): Response
    {
        $cluster = $this->getCluster($request);
        $poolGateway = $this->gatewayRegistry->get($pool);

        $key = $request->request->get('key');

        if ($key !== null && !\is_string($key)) {
            throw IncrementException::keyParameterIsMissing();
        }

        $poolGateway->reset($cluster, $key);

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/api/_action/delete-increment/{pool}', name: 'api.increment.delete', methods: ['DELETE'])]
    public function delete(string $pool, Request $request): Response
    {
        $keys = $request->get('keys', []);

        if (!\is_array($keys)) {
            throw IncrementException::invalidKeysParameter();
        }

        $cluster = $this->getCluster($request);
        $poolGateway = $this->gatewayRegistry->get($pool);

        $poolGateway->delete($cluster, $keys);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function getCluster(Request $request): string
    {
        $cluster = $request->get('cluster');

        if ($cluster && \is_string($cluster)) {
            return $cluster;
        }

        throw IncrementException::clusterParameterIsMissing();
    }
}
