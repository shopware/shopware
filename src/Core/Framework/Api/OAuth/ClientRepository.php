<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Uuid\Uuid;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get a client.
     *
     * @param string      $clientIdentifier   The client's identifier
     * @param string|null $grantType          The grant type used (if sent)
     * @param string|null $clientSecret       The client's secret (if sent)
     * @param bool        $mustValidateSecret If true the client must attempt to validate the secret if the client                                        is confidential
     */
    public function getClientEntity($clientIdentifier, $grantType = null, $clientSecret = null, $mustValidateSecret = true): ?ClientEntityInterface
    {
        if ($grantType === 'password' && $clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        if ($grantType === 'refresh_token' && $clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        if ($grantType === 'client_credentials') {
            return $this->getByAccessKey($clientIdentifier, $clientSecret);
        }

        return null;
    }

    private function getByAccessKey(string $clientIdentifier, string $clientSecret): ?ClientEntityInterface
    {
        $origin = AccessKeyHelper::getOrigin($clientIdentifier);

        if ($origin === 'user') {
            return $this->getUserByAccessKey($clientIdentifier, $clientSecret);
        }

        if ($origin === 'integration') {
            return $this->getIntegrationByAccessKey($clientIdentifier, $clientSecret);
        }

        return null;
    }

    private function getUserByAccessKey(string $clientIdentifier, string $clientSecret): ClientEntityInterface
    {
        // @deprecated tag:v6.4.0 - write_access will be removed
        $key = $this->connection->createQueryBuilder()
            ->select(['user_id', 'secret_access_key', 'write_access'])
            ->from('user_access_key')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientIdentifier)
            ->execute()
            ->fetch();

        if (!$key) {
            throw OAuthServerException::invalidCredentials();
        }

        if (!password_verify($clientSecret, $key['secret_access_key'])) {
            throw OAuthServerException::invalidCredentials();
        }

        return new ApiClient($clientIdentifier, true, Uuid::fromBytesToHex($key['user_id']));
    }

    private function getIntegrationByAccessKey(string $clientIdentifier, string $clientSecret): ClientEntityInterface
    {
        // @deprecated tag:v6.4.0 - write_access will be removed
        $key = $this->connection->createQueryBuilder()
            ->select(['integration.id AS id', 'label', 'secret_access_key', 'write_access', 'app.active as active'])
            ->from('integration')
            ->leftJoin('integration', 'app', 'app', 'app.integration_id = integration.id')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientIdentifier)
            ->execute()
            ->fetch();

        if (!$key) {
            throw OAuthServerException::invalidCredentials();
        }

        // inactive apps cannot access the api
        // if the integration is not associated to an app `active` will be null
        if ($key['active'] === '0') {
            throw OAuthServerException::invalidCredentials();
        }

        if (!password_verify($clientSecret, $key['secret_access_key'])) {
            throw OAuthServerException::invalidCredentials();
        }

        return new ApiClient($clientIdentifier, true, $key['label']);
    }
}
