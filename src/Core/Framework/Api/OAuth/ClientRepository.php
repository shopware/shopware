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
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        if (($grantType === 'password' || $grantType === 'refresh_token') && $clientIdentifier === 'administration') {
            return true;
        }

        if ($grantType === 'client_credentials' && $clientSecret !== null) {
            $values = $this->getByAccessKey($clientIdentifier);

            return password_verify($clientSecret, $values['secret_access_key']);
        }

        // @codeCoverageIgnoreStart
        throw OAuthServerException::unsupportedGrantType();
        // @codeCoverageIgnoreEnd
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        if ($clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        $values = $this->getByAccessKey($clientIdentifier);

        return new ApiClient($clientIdentifier, true, $values['label'] ?? Uuid::fromBytesToHex($values['user_id']));
    }

    private function getByAccessKey(string $clientIdentifier): array
    {
        $origin = AccessKeyHelper::getOrigin($clientIdentifier);

        if ($origin === 'user') {
            return $this->getUserByAccessKey($clientIdentifier);
        }

        if ($origin === 'integration') {
            return $this->getIntegrationByAccessKey($clientIdentifier);
        }

        throw OAuthServerException::invalidCredentials();
    }

    private function getUserByAccessKey(string $clientIdentifier): array
    {
        $key = $this->connection->createQueryBuilder()
            ->select(['user_id', 'secret_access_key'])
            ->from('user_access_key')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientIdentifier)
            ->execute()
            ->fetch();

        if (!$key) {
            throw OAuthServerException::invalidCredentials();
        }

        return $key;
    }

    private function getIntegrationByAccessKey(string $clientIdentifier): array
    {
        $key = $this->connection->createQueryBuilder()
            ->select(['integration.id AS id', 'label', 'secret_access_key', 'app.active as active'])
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

        return $key;
    }
}
