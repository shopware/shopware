<?php

namespace Shopware\Framework\Application;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\HttpFoundation\Request;

class ApplicationResolver implements ApplicationResolverInterface
{
    public const APPLICATION_SECRET_HEADER = 'x-sw-access-key';
    public const CONTEXT_HEADER = 'x-sw-context';

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function resolveApplication(Request $request, ApplicationInfo $appInfo): void
    {
        if (!$request->headers->has(self::APPLICATION_SECRET_HEADER)) {
            throw new ApplicationNotFoundException();
        }

        $accessKey = $request->headers->get(self::APPLICATION_SECRET_HEADER);

        $query = $this->connection->createQueryBuilder();
        $appIdBinary = $query->select(['application.id'])
            ->from('application')
            ->where('access_key = :accessKey')
            ->setParameter('access_key', $accessKey)
            ->setMaxResults(1)
            ->execute()
            ->fetchColumn();

        if (!$appIdBinary) {
            throw new ApplicationNotFoundException();
        }

        $appInfo->setApplicationId(Uuid::fromBytesToHex($appIdBinary));
    }

    public function resolveContextToken(Request $request, ApplicationInfo $appInfo): void
    {
        if (false === $request->headers->has(self::CONTEXT_HEADER)) {
            return;
        }

        $appInfo->setContextId($request->headers->get(self::CONTEXT_HEADER));
    }
}