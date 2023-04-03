<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class TestUser
{
    private function __construct(
        private readonly string $password,
        private readonly string $name,
        private readonly string $userId
    ) {
    }

    public static function getAdmin(): TestUser
    {
        return new TestUser('shopware', 'admin', Uuid::randomHex());
    }

    /**
     * @param list<string> $permissions
     */
    public static function createNewTestUser(Connection $connection, array $permissions = []): TestUser
    {
        $username = Uuid::randomHex();
        $password = Uuid::randomHex();
        $email = Uuid::randomHex();

        $userId = Uuid::randomBytes();
        $avatarId = Uuid::randomBytes();

        $connection->insert('media', [
            'id' => $avatarId,
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'uploaded_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('user', [
            'id' => $userId,
            'first_name' => $username,
            'last_name' => '',
            'email' => "{$email}@example.com",
            'username' => $username,
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'locale_id' => self::getLocaleOfSystemLanguage($connection),
            'active' => 1,
            'admin' => 0,
            'avatar_id' => $avatarId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $roleId = self::buildRole($permissions, $connection);
        if ($roleId) {
            $connection->insert(
                'acl_user_role',
                [
                    'user_id' => $userId,
                    'acl_role_id' => $roleId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        }

        return new TestUser($password, $username, Uuid::fromBytesToHex($userId));
    }

    public function authorizeBrowser(KernelBrowser $browser): void
    {
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $this->name,
            'password' => $this->password,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!\array_key_exists('access_token', $data)) {
            throw new \RuntimeException(
                'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error' . print_r($data, true))
            );
        }

        if (!\array_key_exists('refresh_token', $data)) {
            throw new \RuntimeException(
                'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
            );
        }

        $accessToken = $data['access_token'];
        \assert(\is_string($accessToken));
        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $accessToken));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    private static function getLocaleOfSystemLanguage(Connection $connection): string
    {
        $builder = $connection->createQueryBuilder();

        return (string) $builder->select('locale.id')
            ->from('language', 'language')
            ->innerJoin('language', 'locale', 'locale', 'language.locale_id = locale.id')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param list<string> $permissions
     */
    private static function buildRole(array $permissions, Connection $connection): ?string
    {
        if ($permissions === []) {
            return null;
        }
        $roleId = Uuid::randomBytes();
        $roleName = Uuid::randomHex();

        $connection->insert('acl_role', [
            'id' => $roleId,
            'name' => $roleName,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'privileges' => json_encode($permissions, \JSON_THROW_ON_ERROR),
        ]);

        return $roleId;
    }
}
