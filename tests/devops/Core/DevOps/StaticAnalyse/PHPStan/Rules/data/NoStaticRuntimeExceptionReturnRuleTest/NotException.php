<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStaticRuntimeExceptionReturnRuleTest;

class NotException
{
    public static function create(): \RuntimeException
    {
        return new \RuntimeException('ok');
    }
}
