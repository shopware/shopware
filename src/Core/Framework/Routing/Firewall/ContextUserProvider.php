<?php declare(strict_types=1);

namespace Shopware\Framework\Routing\Firewall;

use Doctrine\DBAL\Connection;
use Shopware\Checkout\Customer\Util\CustomerContextServiceInterface;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ContextUserProvider implements UserProviderInterface
{
    /**
     * @var CustomerContextServiceInterface
     */
    private $storefrontContextService;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(CustomerContextServiceInterface $storefrontContextService, Connection $connection)
    {
        $this->storefrontContextService = $storefrontContextService;
        $this->connection = $connection;
    }

    /**
     * @param ApplicationToken $token
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return ContextUser|UserInterface
     */
    public function loadUserByUsername($token)
    {
        $foundRows = $this->connection->executeQuery(
            'SELECT id FROM application WHERE id = :id',
            ['id' => Uuid::fromStringToBytes($token->getApplicationId())]
        )->rowCount();

        if (!$foundRows) {
            throw new AccessDeniedHttpException(
                sprintf('Application Key "%s" is not valid.', $token->getApplicationId())
            );
        }

        //todo@jb use real tenant id
        $context = $this->storefrontContextService->get(Defaults::TENANT_ID, $token->getApplicationId(), $token->getContextId());

        return new ContextUser($token->getApplicationId(), $context);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof ContextUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername(
            new ApplicationToken($user->getApplicationToken(), $user->getContext()->getToken())
        );
    }

    public function supportsClass($class)
    {
        return $class === ContextUser::class;
    }
}
