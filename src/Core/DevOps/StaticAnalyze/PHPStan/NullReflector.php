<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use PHPStan\Type\Type;
use staabm\PHPStanDba\Error;
use staabm\PHPStanDba\QueryReflection\QueryReflector;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - will be internal in 6.5.0
 */
class NullReflector implements QueryReflector
{
    public function validateQueryString(string $queryString): ?Error
    {
        return null;
    }

    public function getResultType(string $queryString, int $fetchType): ?Type
    {
        return null;
    }
}
