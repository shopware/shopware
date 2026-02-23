<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if (($grantType === 'password' || $grantType === 'refresh_token') && $clientIdentifier === 'administration') {
            return true;
        }

        if ($grantType === 'client_credentials' && $clientSecret !== null) {
            $values = $this->getByAccessKey($clientIdentifier);
            if (!$values) {
                return false;
            }

            if (!password_verify($clientSecret, (string) $values['secret_access_key'])) {
                return false;
            }

            $id = $values['id'] ?? '';
            if ($id !== '') {
                $this->updateLastUsageDate($id);
            }

            return true;
        }

        // @codeCoverageIgnoreStart
        throw OAuthServerException::unsupportedGrantType();
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param non-empty-string $clientIdentifier
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        if ($clientIdentifier === 'administration') {
            return new ApiClient('administration', true, confidential: false);
        }

        $accessKey = $this->getByAccessKey($clientIdentifier);

        if ($accessKey === null) {
            return null;
        }

        $userId = $accessKey['user_id'] ?? null;

        return new ApiClient(
            $clientIdentifier,
            true,
            name: $userId !== null ? Uuid::fromBytesToHex($userId) : $accessKey['label'] ?? '',
            confidential: true
        );
    }

    public function updateLastUsageDate(string $integrationId): void
    {
        $this->connection->update(
            'integration',
            ['last_usage_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            ['id' => $integrationId]
        );
    }

    /**
     * @return array{user_id: string, secret_access_key: string}|array{id: string, label: string, secret_access_key: string}|null
     */
    private function getByAccessKey(string $clientIdentifier): ?array
    {
        $origin = AccessKeyHelper::getOrigin($clientIdentifier);

        if ($origin === 'user') {
            return $this->getUserByAccessKey($clientIdentifier);
        }

        if ($origin === 'integration') {
            return $this->getIntegrationByAccessKey($clientIdentifier);
        }

        return null;
    }

    /**
     * @return array{user_id: string, secret_access_key: string}|null
     */
    private function getUserByAccessKey(string $clientIdentifier): ?array
    {
        /** @var array{user_id: string, secret_access_key: string}|false $key */
        $key = $this->connection->fetchAssociative(
            'SELECT user_id, secret_access_key
             FROM user_access_key
             WHERE access_key = :accessKey',
            ['accessKey' => $clientIdentifier]
        );

        if ($key === false) {
            return null;
        }

        return $key;
    }

    /**
     * @return array{id: string, label: string, secret_access_key: string}|null
     */
    private function getIntegrationByAccessKey(string $clientIdentifier): ?array
    {
        /** @var array{id: string, label: string, active: '1'|'0', secret_access_key: string}|false $key */
        $key = $this->connection->fetchAssociative(
            'SELECT integration.id AS id, label, app.active AS active, secret_access_key
             FROM integration
             LEFT JOIN app ON app.integration_id = integration.id
             WHERE access_key = :accessKey',
            ['accessKey' => $clientIdentifier]
        );

        if ($key === false) {
            return null;
        }

        // inactive apps cannot access the api
        // if the integration is not associated to an app `active` will be null
        if ($key['active'] === '0') {
            return null;
        }
        unset($key['active']);

        return $key;
    }
}
