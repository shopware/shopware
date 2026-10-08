<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\Elsewhere;

class OutsideEnabledNamespace
{
    public function literals(string $value, string $pattern): void
    {
        preg_match('/^[a-z]+$/', $value); // not enabled for this namespace
        preg_match($pattern, $value); // not enabled for this namespace
    }
}
