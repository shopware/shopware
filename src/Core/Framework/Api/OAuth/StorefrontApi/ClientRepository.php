<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\StorefrontApi;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\StorefrontApi\Client\SalesChannelClient;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
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
        $origin = AccessKeyHelper::getOrigin($clientIdentifier);
        if ($origin !== 'sales-channel') {
            OAuthServerException::invalidCredentials();
        }

        $builder = $this->connection->createQueryBuilder();

        $salesChannel = $builder->select(['sales_channel.secret_access_key'])
            ->from('sales_channel')
            ->where('sales_channel.tenant_id = :tenantId')
            ->andWhere('sales_channel.access_key = :accessKey')
            ->setParameter('tenantId', Uuid::fromHexToBytes($this->getTenantId()))
            ->setParameter('accessKey', $clientIdentifier)
            ->execute()
            ->fetch();

        if (!$salesChannel) {
            return null;
        }

        if ($mustValidateSecret === true && !password_verify($clientSecret, $salesChannel['secret_access_key'])) {
            return null;
        }

        return new SalesChannelClient($clientIdentifier);
    }

    private function getTenantId(): string
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            throw OAuthServerException::serverError('The TENANT_ID is missing.');
        }

        return $master->headers->get(PlatformRequest::HEADER_TENANT_ID);
    }
}
