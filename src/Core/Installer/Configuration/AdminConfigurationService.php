<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Configuration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Installer\Controller\ShopConfigurationController;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;

/**
 * @internal
 *
 * @phpstan-import-type AdminUser from ShopConfigurationController
 */
class AdminConfigurationService
{
    /**
     * @param AdminUser $user
     */
    public function createAdmin(array $user, Connection $connection): void
    {
        $userProvisioner = new UserProvisioner($connection);
        $userProvisioner->provision(
            $user['username'],
            $user['password'],
            [
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName'],
                'email' => $user['email'],
            ]
        );
    }
}
