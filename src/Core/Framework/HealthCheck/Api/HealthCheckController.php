<?php declare(strict_types=1);

namespace Shopware\Core\Framework\HealthCheck\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HealthCheck\EventDispatcher\HealthCheckEventDispatcher;
use Shopware\Core\Framework\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class HealthCheckController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HealthCheckEventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * @deprecated tag:v6.7.0 - Parameter $context will be required in v6.7.0.0
     *
     * This is a simple health check to check that the basic application runs.
     * Use it in Docker HEALTHCHECK with curl --silent --fail http://localhost/api/_info/health-check
     */
    #[Route(path: '/api/_info/health-check', name: 'api.info.health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function check(?Context $context = null): Response
    {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'Parameter $context in method `check()` will be required in v6.7.0.0'
            );
        }

        $context ??= Context::createDefaultContext();

        $event = new HealthCheckEvent($context);
        $event = $this->eventDispatcher->dispatch($event);

        return $this->getResponseBody($event);
    }

    /**
     * @param HealthCheckEvent $event
     *
     * @return Response
     */
    private function getResponseBody(HealthCheckEvent $event): Response
    {
        $response = new Response('');
        $response->setPrivate();

        $data = $event->getServiceDataList();

        if ($data === []) {
            return $response;
        }

        $response->setContent(json_encode($data, JSON_PRETTY_PRINT));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
