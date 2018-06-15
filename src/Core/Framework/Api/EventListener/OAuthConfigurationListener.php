<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The OAuth authorization server must be configured before using it.
 * At this point the different grants are registered with the authorization servers.
 */
class OAuthConfigurationListener implements EventSubscriberInterface
{
    /**
     * @var AuthorizationServer
     */
    private $authorizationServer;

    /**
     * @var AuthorizationServer
     */
    private $storefrontApiServer;

    /**
     * @var UserRepositoryInterface
     */
    private $apiUserRepository;

    /**
     * @var RefreshTokenRepositoryInterface
     */
    private $apiRefreshTokenRepository;

    public function __construct(
        AuthorizationServer $apiServer,
        AuthorizationServer $storefrontApiServer,
        UserRepositoryInterface $apiUserRepository,
        RefreshTokenRepositoryInterface $apiRefreshTokenRepository
    ) {
        $this->authorizationServer = $apiServer;
        $this->storefrontApiServer = $storefrontApiServer;
        $this->apiUserRepository = $apiUserRepository;
        $this->apiRefreshTokenRepository = $apiRefreshTokenRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['setupApi', 128],
                ['setupStorefrontApi', 128],
            ],
        ];
    }

    public function setupApi(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $monthInterval = new \DateInterval('P1M');
        $hourInterval = new \DateInterval('PT1H');

        $passwordGrant = new PasswordGrant($this->apiUserRepository, $this->apiRefreshTokenRepository);
        $passwordGrant->setRefreshTokenTTL($monthInterval);

        $refreshTokenGrant = new RefreshTokenGrant($this->apiRefreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL($hourInterval);

        $this->authorizationServer->enableGrantType($passwordGrant, $hourInterval);
        $this->authorizationServer->enableGrantType($refreshTokenGrant, $hourInterval);
        $this->authorizationServer->enableGrantType(new ClientCredentialsGrant(), $hourInterval);
    }

    public function setupStorefrontApi(GetResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $this->storefrontApiServer->enableGrantType(new ClientCredentialsGrant(), new \DateInterval('PT1H'));
    }
}
