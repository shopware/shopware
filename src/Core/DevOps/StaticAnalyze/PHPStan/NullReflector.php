<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use PHPStan\Type\Type;
use staabm\PHPStanDba\Error;
use staabm\PHPStanDba\QueryReflection\QueryReflector;

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
