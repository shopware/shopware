<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Api;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Api\Client\AdministrationClient;
use Shopware\Core\Framework\Struct\Uuid;

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
     * @param null|string $grantType          The grant type used (if sent)
     * @param null|string $clientSecret       The client's secret (if sent)
     * @param bool        $mustValidateSecret If true the client must attempt to validate the secret if the client
     *                                        is confidential
     *
     * @return ClientEntityInterface
     */
    public function getClientEntity($clientIdentifier, $grantType = null, $clientSecret = null, $mustValidateSecret = true)
    {
        if ($grantType === 'password' && $clientIdentifier === 'administration') {
            return new AdministrationClient();
        }

        if ($grantType === 'refresh_token' && $clientIdentifier === 'administration') {
            return new AdministrationClient();
        }

        if ($grantType === 'client_credentials') {
            $user = $this->getUserByAccessKey($clientIdentifier, $clientSecret);

            return new AdministrationClient(Uuid::fromBytesToHex($user['user_id']), (bool) $user['write_access']);
        }

        return null;
    }

    private function getUserByAccessKey(string $clientIdentifier, string $clientSecret): array
    {
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
            OAuthServerException::invalidCredentials();
        }

        return $key;
    }
}
