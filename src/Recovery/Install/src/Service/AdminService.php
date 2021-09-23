<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\User\Service\UserProvisioner;
use Shopware\Recovery\Install\Struct\AdminUser;

class AdminService
{
    private Connection $connection;

    private UserProvisioner $userProvisioner;

    public function __construct(Connection $connection, UserProvisioner $userProvisioner)
    {
        $this->connection = $connection;
        $this->userProvisioner = $userProvisioner;
    }

    public function createAdmin(AdminUser $user): void
    {
        $localeId = $this->getLocaleId($user);

        $this->userProvisioner->provision(
            $user->username,
            $user->password,
            [
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'localeId' => $localeId,
            ]
        );
    }

    private function getLocaleId(AdminUser $user): string
    {
        $sql = 'SELECT locale.id FROM language INNER JOIN locale ON(locale.id = language.locale_id) WHERE language.id = ?';

        $localeId = $this->connection->prepare($sql);
        $localeId->execute([Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);
        $localeId = $localeId->fetchColumn();

        if (!$localeId) {
            throw new \RuntimeException('Could not resolve language ' . $user->locale);
        }

        return (string) $localeId;
    }
}
