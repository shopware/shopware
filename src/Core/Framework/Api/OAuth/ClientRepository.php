<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\RequestStack;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(Connection $connection, RequestStack $requestStack)
    {
        $this->connection = $connection;
        $this->requestStack = $requestStack;
    }

    /**
     * do not validate oauth client here, as this would mean two requests:
     * 1. to validate the client
     * 2. to actually fetch the client
     * instead, the client_credentials grant will throw en exception if the client secret is invalid on its own
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        return true;
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $request = $this->requestStack->getMasterRequest();

        if (!$request) {
            return null;
        }

        $grantType = $request->request->get('grant_type');

        if ($grantType === 'password' && $clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        if ($grantType === 'refresh_token' && $clientIdentifier === 'administration') {
            return new ApiClient('administration', true);
        }

        if ($grantType === 'client_credentials') {
            $clientSecret = $request->request->get('client_secret');

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

        if (!password_verify($clientSecret, $key['secret_access_key'])) {
            throw OAuthServerException::invalidCredentials();
        }

        return new ApiClient($clientIdentifier, true, Uuid::fromBytesToHex($key['user_id']));
    }

    private function getIntegrationByAccessKey(string $clientIdentifier, string $clientSecret): ClientEntityInterface
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

        if (!password_verify($clientSecret, $key['secret_access_key'])) {
            throw OAuthServerException::invalidCredentials();
        }

        return new ApiClient($clientIdentifier, true, $key['label']);
    }
}
