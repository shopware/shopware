<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\PregDollarWithoutDRuleExtra;

class EnabledNamespacePrefix
{
    public function literals(string $value): void
    {
        preg_match('/^[a-z]+$/', $value); // not enabled: the namespace only shares a name prefix with an enabled one
    }
}
