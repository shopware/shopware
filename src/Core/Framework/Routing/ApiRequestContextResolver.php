<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\PlatformRequest;
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

        $config = $this->getRuntimeParameters($master);
        $userId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID);
        $tenantId = $master->headers->get(PlatformRequest::HEADER_TENANT_ID);

        if ($userId) {
            $config = array_replace_recursive($this->getUserParameters($userId, $tenantId), $config);
        }

        $currencyFactory = 1.0;

        $context = new Context(
            $tenantId,
            '',
            null,
            [],
            $config['currencyId'],
            $config['languageId'],
            $config['languageId'],
            //$config['fallbackLanguageId'],
            Defaults::LIVE_VERSION,
            $currencyFactory
        );

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);
    }

    private function getRuntimeParameters(Request $request): array
    {
        $parameters = [
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE,
        ];

        if ($request->headers->has('language')) {
            $parameters['languageId'] = $request->headers->get('language');
        }

        return $parameters;
    }

    private function getUserParameters(string $userId, string $tenantId): array
    {
        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select([
                '"ffffffffffffffffffffffffffffffff" as languageId', //'user.languageId',
                '"4c8eba11bd3546d786afbed481a6e665" as currencyId', //'user.currencyId',
                /*'user.tenant_id'*/
            ])
            ->from('user')
            ->where('id = :userId')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->setParameter('tenantId', Uuid::fromHexToBytes($tenantId))
            ->execute()
            ->fetch();

        if ($user) {
            return $user;
        }

        return [];
    }
}
