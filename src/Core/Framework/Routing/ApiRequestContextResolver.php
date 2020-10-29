<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RouteScopeRegistry
     */
    private $routeScopeRegistry;

    public function __construct(
        Connection $connection,
        RouteScopeRegistry $routeScopeRegistry
    ) {
        $this->connection = $connection;
        $this->routeScopeRegistry = $routeScopeRegistry;
    }

    public function resolve(Request $request): void
    {
        if ($request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)) {
            return;
        }

        if (!$this->isRequestScoped($request, ApiContextRouteScopeDependant::class)) {
            return;
        }

        $params = $this->getContextParameters($request);
        $languageIdChain = $this->getLanguageIdChain($params);

        $context = new Context(
            $this->resolveContextSource($request),
            [],
            $params['currencyId'],
            $languageIdChain,
            $params['versionId'] ?? Defaults::LIVE_VERSION,
            $params['currencyFactory'],
            $params['currencyPrecision'],
            $params['considerInheritance']
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function getContextParameters(Request $request)
    {
        $params = [
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'systemFallbackLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyFactory' => 1.0,
            'currencyPrecision' => 2,
            'versionId' => $request->headers->get(PlatformRequest::HEADER_VERSION_ID),
            'considerInheritance' => false,
        ];

        $runtimeParams = $this->getRuntimeParameters($request);
        $params = array_replace_recursive($params, $runtimeParams);

        return $params;
    }

    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [];

        if ($request->headers->has(PlatformRequest::HEADER_LANGUAGE_ID)) {
            $langHeader = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);

            if ($langHeader !== null) {
                $parameters['languageId'] = $langHeader;
            }
        }

        if ($request->headers->has(PlatformRequest::HEADER_CURRENCY_ID)) {
            $currencyHeader = $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID);

            if ($currencyHeader !== null) {
                $parameters['currencyId'] = $currencyHeader;
            }
        }

        if ($request->headers->has(PlatformRequest::HEADER_INHERITANCE)) {
            $parameters['considerInheritance'] = true;
        }

        return $parameters;
    }

    private function resolveContextSource(Request $request): ContextSource
    {
        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return new SalesChannelApiSource(Defaults::SALES_CHANNEL);
        }

        if ($userId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID)) {
            return $this->getAdminApiSource($userId);
        }

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)) {
            return new SystemSource();
        }

        $clientId = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);
        $keyOrigin = AccessKeyHelper::getOrigin($clientId);

        if ($keyOrigin === 'user') {
            $userId = $this->getUserIdByAccessKey($clientId);

            return $this->getAdminApiSource($userId);
        }

        if ($keyOrigin === 'integration') {
            $integrationId = $this->getIntegrationIdByAccessKey($clientId);

            return $this->getAdminApiSource(null, $integrationId);
        }

        if ($keyOrigin === 'sales-channel') {
            $salesChannelId = $this->getSalesChannelIdByAccessKey($clientId);

            return new SalesChannelApiSource($salesChannelId);
        }

        return new SystemSource();
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

    private function getSalesChannelIdByAccessKey(string $clientId): string
    {
        $id = $this->connection->createQueryBuilder()
            ->select(['id'])
            ->from('sales_channel')
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

    private function getAdminApiSource(?string $userId, ?string $integrationId = null): AdminApiSource
    {
        $source = new AdminApiSource($userId, $integrationId);

        // Use the permissions associated to that app, if the request is made by an integration associated to an app
        $appPermissions = $this->fetchPermissionsIntegrationByApp($integrationId);
        if ($appPermissions !== null) {
            $source->setIsAdmin(false);
            $source->setPermissions($appPermissions);

            return $source;
        }

        if ($userId !== null) {
            $source->setPermissions($this->fetchPermissions($userId));
            $source->setIsAdmin($this->isAdmin($userId));

            return $source;
        }

        if ($integrationId !== null) {
            $source->setIsAdmin($this->isAdminIntegration($integrationId));
            $source->setPermissions($this->fetchIntegrationPermissions($integrationId));

            return $source;
        }

        return $source;
    }

    private function isAdmin(string $userId): bool
    {
        return (bool) $this->connection->fetchColumn(
            'SELECT admin FROM `user` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($userId)]
        );
    }

    private function isAdminIntegration(string $integrationId): bool
    {
        return (bool) $this->connection->fetchColumn(
            'SELECT admin FROM `integration` WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($integrationId)]
        );
    }

    private function fetchPermissions(string $userId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select(['role.privileges'])
            ->from('acl_user_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->execute()
            ->fetchAll(FetchMode::COLUMN);

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }

    private function fetchPermissionsIntegrationByApp(?string $integrationId): ?array
    {
        if (!$integrationId) {
            return null;
        }

        $privileges = $this->connection->fetchColumn('
            SELECT `acl_role`.`privileges`
            FROM `acl_role`
            INNER JOIN `app` ON `app`.`acl_role_id` = `acl_role`.`id`
            WHERE `app`.`integration_id` = :integrationId
        ', ['integrationId' => Uuid::fromHexToBytes($integrationId)]);

        if ($privileges === false) {
            return null;
        }

        return json_decode($privileges, true);
    }

    private function fetchIntegrationPermissions(string $integrationId): array
    {
        $permissions = $this->connection->createQueryBuilder()
            ->select(['role.privileges'])
            ->from('integration_role', 'mapping')
            ->innerJoin('mapping', 'acl_role', 'role', 'mapping.acl_role_id = role.id')
            ->where('mapping.integration_id = :integrationId')
            ->setParameter('integrationId', Uuid::fromHexToBytes($integrationId))
            ->execute()
            ->fetchAll(FetchMode::COLUMN);

        $list = [];
        foreach ($permissions as $privileges) {
            $privileges = json_decode((string) $privileges, true);
            $list = array_merge($list, $privileges);
        }

        return array_unique(array_filter($list));
    }
}
