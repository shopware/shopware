<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Util;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;

class StatementHelper
{
    public static function executeStatement(Statement $stmt, array $parameters = []): int|string
    {
        self::bindParameters($stmt, $parameters);

        return $stmt->executeStatement();
    }

    public static function executeQuery(Statement $stmt, array $parameters = []): Result
    {
        self::bindParameters($stmt, $parameters);

        return $stmt->executeQuery();
    }

    public static function bindParameters(Statement $stmt, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value);
        }
    }
}
