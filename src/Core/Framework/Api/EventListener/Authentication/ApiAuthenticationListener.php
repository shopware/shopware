<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener\Authentication;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
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

    private static $protectedAdditions = [
        '/admin/v1/search',
    ];

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

    public function __construct(
        ResourceServer $resourceServer,
        AuthorizationServer $authorizationServer,
        UserRepositoryInterface $userRepository,
        RefreshTokenRepositoryInterface $refreshTokenRepository
    ) {
        $this->resourceServer = $resourceServer;
        $this->authorizationServer = $authorizationServer;
        $this->userRepository = $userRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public static function getSubscribedEvents()
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

        $whitelisted = $this->isWhitelistRoute($request->getPathInfo());

        if (stripos($request->getPathInfo(), self::$routePrefix) !== 0 && !$whitelisted) {
            return;
        }

        $psr7Factory = new DiactorosFactory();
        $psr7Request = $psr7Factory->createRequest($event->getRequest());
        $psr7Request = $this->resourceServer->validateAuthenticatedRequest($psr7Request);

        $request->attributes->add($psr7Request->getAttributes());
    }

    private function isWhitelistRoute(string $pathInfo): bool
    {
        foreach (self::$protectedAdditions as $route) {
            if (stripos($pathInfo, $route) === 0) {
                return true;
            }
        }

        return false;
    }
}
