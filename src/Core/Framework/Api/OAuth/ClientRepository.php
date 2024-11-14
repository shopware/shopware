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

#[Package('core')]
class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        if (($grantType === 'password' || $grantType === 'refresh_token') && $clientIdentifier === 'administration') {
            return true;
        }

        if ($grantType === 'client_credentials' && $clientSecret !== null) {
            if (!\is_string($clientIdentifier)) {
                return false;
            }

            $values = $this->getByAccessKey($clientIdentifier);
            if (!$values) {
                return false;
            }

            if (!password_verify($clientSecret, (string) $values['secret_access_key'])) {
                return false;
            }

            if (!empty($values['id'])) {
                $this->updateLastUsageDate($values['id']);
            }

            return true;
        }

        // @codeCoverageIgnoreStart
        throw OAuthServerException::unsupportedGrantType();
        // @codeCoverageIgnoreEnd
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        if (!\is_string($clientIdentifier)) {
            return null;
        }

        if ($clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        $values = $this->getByAccessKey($clientIdentifier);

        if (!$values) {
            return null;
        }

        return new ApiClient($clientIdentifier, true, $values['label'] ?? Uuid::fromBytesToHex((string) $values['user_id']));
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
     * @return array<string, string|null>|null
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
     * @return array<string, string|null>|null
     */
    private function getUserByAccessKey(string $clientIdentifier): ?array
    {
        $key = $this->connection->fetchAssociative('SELECT user_id, secret_access_key FROM user_access_key WHERE access_key = :accessKey', [
            'accessKey' => $clientIdentifier,
        ]);

        if (!$key) {
            return null;
        }

        return $key;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function getIntegrationByAccessKey(string $clientIdentifier): ?array
    {
        $key = $this->connection->fetchAssociative('SELECT integration.id AS id, label, app.active AS active, secret_access_key FROM integration LEFT JOIN app ON app.integration_id = integration.id WHERE access_key = :accessKey', [
            'accessKey' => $clientIdentifier,
        ]);

        if (!$key) {
            return null;
        }

        // inactive apps cannot access the api
        // if the integration is not associated to an app `active` will be null
        if ($key['active'] === '0') {
            return null;
        }

        return $key;
    }
}
