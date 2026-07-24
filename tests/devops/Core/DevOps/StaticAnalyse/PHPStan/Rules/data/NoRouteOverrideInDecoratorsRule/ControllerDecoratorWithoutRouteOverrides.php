<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRouteOverrideInDecoratorsRule;

class ControllerDecoratorWithoutRouteOverrides
{
    public function dummy(): void
    {
    }
}
