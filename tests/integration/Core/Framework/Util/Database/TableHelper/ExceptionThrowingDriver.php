<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Util\Database\TableHelper;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\API\ExceptionConverter;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

/**
 * @internal
 */
class ExceptionThrowingDriver implements Driver
{
    public function __construct(private readonly Driver $innerDriver)
    {
    }

    public function connect(#[\SensitiveParameter] array $params): DriverConnection
    {
        $innerConnection = $this->innerDriver->connect($params);

        return new ExceptionThrowingConnection($innerConnection);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->innerDriver->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): ExceptionConverter
    {
        return $this->innerDriver->getExceptionConverter();
    }
}
