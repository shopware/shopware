<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiAuthenticationListener implements EventSubscriberInterface
{
    /**
     * @var ResourceServer
     */
    private $resourceServer;

    /**
     * @var string
     */
    private static $routePrefix = '/api/';

    /**
     * @var string[]
     */
    private static $unprotectedRoutes = [
        '/api/oauth/',
        '/api/v1/_info/swagger.html',
        '/api/v1/_info/openapi3.json',
        '/api/v1/_info/entity-schema.json',
        '/api/v1/_info/events.json',
        '/api/v1/_action/user/user-recovery',
        '/api/v1/_action/user/user-recovery/hash',
        '/api/v1/_action/user/user-recovery/password',
    ];

    /**
     * @var AuthorizationServer
     */
    private $authorizationServer;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var RefreshTokenRepositoryInterface
     */
    private $refreshTokenRepository;

    /**
     * @var PsrHttpFactory
     */
    private $psrHttpFactory;

    public function __construct(
        ResourceServer $resourceServer,
        AuthorizationServer $authorizationServer,
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        PsrHttpFactory $psrHttpFactory
    ) {
        $this->resourceServer = $resourceServer;
        $this->authorizationServer = $authorizationServer;
        $this->userRepository = $userRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->psrHttpFactory = $psrHttpFactory;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setupOAuth', 128],
                ['validateRequest', 32],
            ],
        ];
    }

    public function setupOAuth(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $monthInterval = new \DateInterval('P1M');
        $hourInterval = new \DateInterval('PT1H');

        $passwordGrant = new PasswordGrant($this->userRepository, $this->refreshTokenRepository);
        $passwordGrant->setRefreshTokenTTL($monthInterval);

        $refreshTokenGrant = new RefreshTokenGrant($this->refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL($hourInterval);

        $this->authorizationServer->enableGrantType($passwordGrant, $hourInterval);
        $this->authorizationServer->enableGrantType($refreshTokenGrant, $hourInterval);
        $this->authorizationServer->enableGrantType(new ClientCredentialsGrant(), $hourInterval);
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

        $psr7Request = $this->psrHttpFactory->createRequest($event->getRequest());
        $psr7Request = $this->resourceServer->validateAuthenticatedRequest($psr7Request);

        $request->attributes->add($psr7Request->getAttributes());
    }
}
