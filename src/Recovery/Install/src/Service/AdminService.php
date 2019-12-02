<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Recovery\Install\Struct\AdminUser;

class AdminService
{
    /**
     * @var \PDO
     */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public function createAdmin(AdminUser $user): void
    {
        $localeId = $this->getLocaleId($user);

        $sql = <<<'EOT'
INSERT INTO user
(id,first_name,last_name,email,username,`password`,locale_id,active,created_at)
VALUES
(?,?,?,?,?,?,?,1,NOW());
EOT;

        $prepareStatement = $this->connection->prepare($sql);
        $prepareStatement->execute([
            Uuid::randomBytes(),
            $user->firstName,
            $user->lastName,
            $user->email,
            $user->username,
            password_hash($user->password, PASSWORD_BCRYPT),
            $localeId,
        ]);
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
