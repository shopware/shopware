<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;

/**
 * @internal
 */
#[Package('framework')]
class AdminConfigurationService
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * @param array{
     *     username: string,
     *     password: string,
     *     firstName?: string,
     *     lastName?: string,
     *     email?: string,
     *     localeId?: string,
     *     admin?: bool
     * } $user
     */
    public function createAdmin(array $user, Connection $connection): void
    {
        $userProvisioner = new UserProvisioner($connection, $this->clock);
        $userName = $user['username'];
        $password = $user['password'];
        unset($user['username'], $user['password']);

        $userProvisioner->provision(
            $userName,
            $password,
            $user,
        );
    }
}
