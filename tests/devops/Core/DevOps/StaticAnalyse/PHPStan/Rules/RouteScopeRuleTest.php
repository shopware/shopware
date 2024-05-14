<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\RouteScopeRule;

/**
 * @internal
 *
 * @extends RuleTestCase<RouteScopeRule>
 */
#[CoversClass(RouteScopeRule::class)]
class RouteScopeRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRouteScopeRule(): void
    {
        $this->analyse([__DIR__ . '/data/RouteScope/ControllerWithRouteAttribute.php'], [
            [
                'Method Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\RouteScope\ControllerWithRouteAttribute::resetScope() has no route scope defined. Please add a route scope to the method or the class.',
                22,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/RouteScope/ControllerWithoutRouteAttribute.php'], [
            [
                'Method Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\RouteScope\ControllerWithoutRouteAttribute::withoutScope() has no route scope defined. Please add a route scope to the method or the class.',
                15,
            ],
        ]);
    }

    /**
     * @return RouteScopeRule
     */
    protected function getRule(): Rule
    {
        return new RouteScopeRule();
    }
}
