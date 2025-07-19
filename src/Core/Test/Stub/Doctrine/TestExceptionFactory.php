<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TestExceptionFactory
{
    public static function createException(string $message): Exception
    {
        return new class($message) extends \Exception implements Exception {
            public function __construct(string $message)
            {
                parent::__construct($message);
            }
        };
    }

    public static function createDriverException(string $message): Exception\DriverException
    {
        return new Exception\DriverException(
            new \Doctrine\DBAL\Driver\PDO\Exception($message),
            null,
        );
    }
}
