<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class HealthCheckController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
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
        $this->eventDispatcher->dispatch($event);

        $response = new Response('');
        $response->setPrivate();

        return $response;
    }
}
