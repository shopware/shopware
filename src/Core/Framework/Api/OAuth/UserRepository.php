<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Framework\Api\OAuth\User\User;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class UserRepository implements UserRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LoginConfigService $loginConfigService,
    ) {
    }

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        if ($this->loginConfigService->getConfig()?->useDefault === false) {
            // never allow login via password if the default login is disabled (e.g. using SSO only)
            return null;
        }

        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select('user.id', 'user.password')
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->fetchAssociative();

        if (!$user) {
            return null;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        return new User(Uuid::fromBytesToHex($user['id']));
    }
}
