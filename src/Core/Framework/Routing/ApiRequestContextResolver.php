<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\StorefrontRequest;
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
        $languageIdChain = $this->getLanguageIdChain($params);

        $context = new Context(
            $sourceContext,
            [],
            $params['currencyId'],
            $languageIdChain,
            $params['versionId'] ?? Defaults::LIVE_VERSION,
            $params['currencyFactory']
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function getContextParameters(Request $master, SourceContext $sourceContext)
    {
        $params = [
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'systemFallbackLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyFactory' => 1.0,
            'versionId' => $master->headers->get(PlatformRequest::HEADER_VERSION_ID),
        ];

        $runtimeParams = $this->getRuntimeParameters($master);
        $params = array_replace_recursive($params, $runtimeParams);

        return $params;
    }

    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];
        if (!$request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            return $parameters;
        }

        $langHeader = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
        if ($langHeader !== null) {
            $parameters['languageId'] = $langHeader;
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

    private function getLanguageIdChain(array $params): array
    {
        $chain = [$params['languageId']];
        if ($chain[0] === Defaults::LANGUAGE_SYSTEM) {
            return $chain; // no query needed
        }
        // `Context` ignores nulls and duplicates
        $chain[] = $this->getParentLanguageId($chain[0]);
        $chain[] = $params['systemFallbackLanguageId'];

        return $chain;
    }

    private function getParentLanguageId($languageId): ?string
    {
        if (!$languageId || !Uuid::isValid($languageId)) {
            throw new LanguageNotFoundException($languageId);
        }
        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.parent_id))'])
            ->from('language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->execute()
            ->fetchAll(FetchMode::COLUMN);

        if (empty($data)) {
            throw new LanguageNotFoundException($languageId);
        }

        return $data[0];
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
