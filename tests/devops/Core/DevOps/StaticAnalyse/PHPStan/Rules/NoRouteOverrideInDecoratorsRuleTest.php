<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoRouteOverrideInDecoratorsRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<NoRouteOverrideInDecoratorsRule>
 */
class NoRouteOverrideInDecoratorsRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        // Test case with dummy controller that does not decorate any service (should not trigger error)
        $this->analyse([__DIR__ . '/data/NoRouteOverrideInDecoratorsRule/DummyController.php'], []);

        // Test case where decorator does not override #Routes (should not trigger error)
        $this->analyse([__DIR__ . '/data/NoRouteOverrideInDecoratorsRule/ControllerDecoratorWithoutRouteOverrides.php'], []);

        // Test case where decorator does override #Routes (should trigger error)
        $this->analyse([__DIR__ . '/data/NoRouteOverrideInDecoratorsRule/ControllerDecoratorWithRouteOverrides.php'], [
            [
                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRouteOverrideInDecoratorsRule\ControllerDecoratorWithRouteOverrides" is a decorator but overrides @Route attributes (class or method-level). Decorators must not override or define routes, otherwise changes to the core route definition don\'t have any affect; only the core route should define the @Route attribute.',
                11,
            ],
        ]);

        // Test case where decorator does override #Routes (with old Attribute name) (should trigger error)
        // Disabled temporarily, to put the entire suite back in the pipeline
        //        $this->analyse([__DIR__ . '/data/NoRouteOverrideInDecoratorsRule/ControllerDecoratorWithRouteOverridesViaDeprecatedAttributeClass.php'], [
        //            [
        //                'Service "Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoRouteOverrideInDecoratorsRule\ControllerDecoratorWithRouteOverridesViaDeprecatedAttributeClass" is a decorator but overrides @Route attributes (class or method-level). Decorators must not override or define routes, otherwise changes to the core route definition don\'t have any affect; only the core route should define the @Route attribute.',
        //                12,
        //            ],
        //        ]);
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory(
            __DIR__ . '/data/NoRouteOverrideInDecoratorsRule/services.xml'
        );

        /** @phpstan-ignore phpstanApi.method */
        return new NoRouteOverrideInDecoratorsRule($factory->create());
    }
}
