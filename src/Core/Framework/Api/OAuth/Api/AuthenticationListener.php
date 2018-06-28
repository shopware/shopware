<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Api;

use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var ResourceServer
     */
    private $server;

    /**
     * @var string
     */
    private static $routePrefix = '/api/';

    /**
     * @var string[]
     */
    private static $unprotectedRoutes = [
        '/api/oauth/',
        '/api/v1/info',
        '/api/v1/info.yaml',
        '/api/v1/info.json',
        '/api/v1/entity-schema.json',
    ];

    public function __construct(ResourceServer $server)
    {
        $this->server = $server;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['validateRequest', 32],
        ];
    }

    public function validateRequest(GetResponseEvent $event): void
    {
        $request = $event->getRequest();

        foreach (self::$unprotectedRoutes as $route) {
            if (stripos($request->getPathInfo(), $route) === 0) {
                return;
            }
        }

        if (stripos($request->getPathInfo(), self::$routePrefix) !== 0) {
            return;
        }

        $psr7Factory = new DiactorosFactory();
        $psr7Request = $psr7Factory->createRequest($event->getRequest());
        $psr7Request = $this->server->validateAuthenticatedRequest($psr7Request);

        $request->attributes->add($psr7Request->getAttributes());
    }
}
