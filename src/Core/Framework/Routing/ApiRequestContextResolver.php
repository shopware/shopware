<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\StorefrontRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRequestContextResolver implements RequestContextResolverInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function resolve(Request $master, Request $request): void
    {
        //sub requests can use context of master
        if ($master->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)) {
            $request->attributes->set(
                PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
                $master->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
            );

            return;
        }

        $sourceContext = $this->resolveSourceContext($request);
        $params = $this->getContextParameters($master, $sourceContext);

        $context = new Context(
            $params['tenantId'],
            $sourceContext,
            null,
            [],
            $params['currencyId'],
            $params['languageId'],
            $params['languageId'],
            //$config['fallbackLanguageId'],
            Defaults::LIVE_VERSION,
            $params['currencyFactory']
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function getContextParameters(Request $master, SourceContext $sourceContext)
    {
        $params = [
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE,
            'currencyFactory' => 1.0,
            'tenantId' => $master->headers->get(PlatformRequest::HEADER_TENANT_ID),
        ];

        if ($sourceContext->getUserId()) {
            $params = array_replace_recursive($params, $this->getUserParameters($sourceContext->getUserId(), $params['tenantId']));
        }

        $params = array_replace_recursive($params, $this->getRuntimeParameters($master));

        return $params;
    }

    private function getUserParameters(string $userId, string $tenantId): array
    {
        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select([
                '"20080911ffff4fffafffffff19830531" as languageId', //'user.languageId',
                '"20080911ffff4fffafffffff19830531" as currencyId', //'user.currencyId',
                /*'user.tenant_id'*/
            ])
            ->from('user')
            ->where('id = :userId')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId))
            ->execute()
            ->fetch();

        return $user ?: [];
    }

    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];

        if ($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            $parameters['languageId'] = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
        }

        return $parameters;
    }

    private function resolveSourceContext(Request $request): SourceContext
    {
        $origin = SourceContext::ORIGIN_API;
        if ($request->attributes->has(StorefrontRequest::ATTRIBUTE_IS_STOREFRONT_REQUEST)) {
            $origin = SourceContext::ORIGIN_STOREFRONT_API;
        }

        $sourceContext = new SourceContext($origin);

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)) {
            return $sourceContext;
        }

        if ($userId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            $sourceContext->setUserId($userId);

            return $sourceContext;
        }

        $clientId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $keyOrigin = AccessKeyHelper::getOrigin($clientId);

        if ($keyOrigin === 'user') {
            $sourceContext->setUserId($this->getUserIdByAccessKey($clientId));
        } elseif ($keyOrigin === 'integration') {
            $sourceContext->setIntegrationId($this->getIntegrationIdByAccessKey($clientId));
        }

        return $sourceContext;
    }

    private function getUserIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select(['user_id'])
            ->from('user_access_key')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientId)
            ->execute()
            ->fetchColumn();

        return Uuid::fromBytesToHex($id);
    }

    private function getIntegrationIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select(['id'])
            ->from('integration')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $clientId)
            ->execute()
            ->fetchColumn();

        return Uuid::fromBytesToHex($id);
    }
}
