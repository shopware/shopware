<?php declare(strict_types=1);

namespace Shopware\Rest\Firewall;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AuthProvider implements UserProviderInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function loadUserByUsername($username): UserInterface
    {
        $user = $this->connection->createQueryBuilder()
            ->select(['*'])
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->execute()
            ->fetch();

        if (!$user) {
            throw new UsernameNotFoundException(sprintf('User with name "%s" was not found.', $username));
        }

        $user = new User(
            $user['username'],
            $user['password'],
            ['IS_AUTHENTICATED_FULLY', 'ROLE_ADMIN']
        );

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass($class): bool
    {
        return $class === User::class;
    }
}
