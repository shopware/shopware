<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment\Controller;

use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class IncrementApiController
{
    private IncrementGatewayRegistry $gatewayRegistry;

    /**
     * @internal
     */
    public function __construct(IncrementGatewayRegistry $gatewayRegistry)
    {
        $this->gatewayRegistry = $gatewayRegistry;
    }

    /**
     * @Since("6.4.6.0")
     * @Route("/api/_action/increment/{pool}", name="api.increment.increment", methods={"POST"})
     */
    public function increment(Request $request, string $pool): Response
    {
        $key = $request->request->get('key');

        if (!$key || !\is_string($key)) {
            throw new \InvalidArgumentException('Increment key must be null or a string');
        }

        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $poolGateway->increment($cluster, $key);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Since("6.4.6.0")
     * @Route("/api/_action/decrement/{pool}", name="api.increment.decrement", methods={"POST"})
     */
    public function decrement(Request $request, string $pool): Response
    {
        $key = $request->request->get('key');

        if (!$key || !\is_string($key)) {
            throw new \InvalidArgumentException('Increment key must be null or a string');
        }

        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $poolGateway->decrement(
            $cluster,
            $key
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Since("6.4.6.0")
     * @Route("/api/_action/increment/{pool}", name="api.increment.list", methods={"GET"})
     */
    public function getIncrement(string $pool, Request $request): Response
    {
        $cluster = $this->getCluster($request);

        $poolGateway = $this->gatewayRegistry->get($pool);

        $limit = $request->query->getInt('limit', 5);
        $offset = $request->query->getInt('offset', 0);

        $result = $poolGateway->list($cluster, $limit, $offset);

        return new JsonResponse($result);
    }

    /**
     * @Since("6.4.6.0")
     * @Route("/api/_action/reset-increment/{pool}", name="api.increment.reset", methods={"POST"})
     */
    public function reset(string $pool, Request $request): Response
    {
        $cluster = $this->getCluster($request);
        $poolGateway = $this->gatewayRegistry->get($pool);

        $key = $request->request->get('key');

        if ($key !== null && !\is_string($key)) {
            throw new \InvalidArgumentException('Increment key must be null or a string');
        }

        $poolGateway->reset($cluster, $key);

        return new JsonResponse(['success' => true]);
    }

    private function getCluster(Request $request): string
    {
        $cluster = $request->get('cluster');

        if ($cluster && \is_string($cluster)) {
            return $cluster;
        }

        throw new \InvalidArgumentException('Argument cluster is missing or invalid');
    }
}
