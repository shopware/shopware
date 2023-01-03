<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\User\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @package core
 */
class UserProvisioner
{
    private Connection $connection;

    private SystemConfigService $systemConfigService;

    /**
     * @internal
     */
    public function __construct(Connection $connection, SystemConfigService $systemConfigService)
    {
        $this->connection = $connection;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param array{firstName?: string, lastName?: string, email?: string, localeId?: string, admin?: bool} $additionalData
     */
    public function provision(string $username, ?string $password = null, array $additionalData = []): string
    {
        if ($this->userExists($username)) {
            throw new \RuntimeException(sprintf('User with username "%s" already exists.', $username));
        }

        $configKey = PasswordFieldSerializer::CONFIG_MIN_LENGTH_FOR[PasswordField::FOR_ADMIN];

        $minPasswordLength = $this->systemConfigService->getInt($configKey);

        if ($password && strlen($password) <= $minPasswordLength) {
            throw new \InvalidArgumentException(sprintf('The password length cannot be shorter than %s characters.', $minPasswordLength));
        }

        $password = $password ?? Random::getAlphanumericString($minPasswordLength > 0 ? $minPasswordLength : 8);

        $userPayload = [
            'id' => Uuid::randomBytes(),
            'first_name' => $additionalData['firstName'] ?? '',
            'last_name' => $additionalData['lastName'] ?? $username,
            'email' => $additionalData['email'] ?? 'info@shopware.com',
            'username' => $username,
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'locale_id' => $additionalData['localeId'] ?? $this->getLocaleOfSystemLanguage(),
            'active' => true,
            'admin' => $additionalData['admin'] ?? true,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->insert('user', $userPayload);

        return $password;
    }

    private function userExists(string $username): bool
    {
        $builder = $this->connection->createQueryBuilder();

        return $builder->select('1')
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->execute()
            ->rowCount() > 0;
    }

    private function getLocaleOfSystemLanguage(): string
    {
        $builder = $this->connection->createQueryBuilder();

        return (string) $builder->select('locale.id')
                ->from('language', 'language')
                ->innerJoin('language', 'locale', 'locale', 'language.locale_id = locale.id')
                ->where('language.id = :id')
                ->setParameter('id', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
                ->execute()
                ->fetchOne();
    }
}
