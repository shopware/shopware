<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class HealthCheckController
{
    /**
     * This is a simple health check to check that the basic application runs.
     * Use it in Docker HEALTHCHECK with curl --silent --fail http://localhost/api/_info/health-check
     */
    #[Route(path: '/api/_info/health-check', name: 'api.info.health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function check(): Response
    {
        $response = new Response('');
        $response->setPrivate();

        return $response;
    }
}
