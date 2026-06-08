<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\ServiceRegisteredInCorrectBundleRule;

/**
 * @internal
 *
 * @extends RuleTestCase<ServiceRegisteredInCorrectBundleRule>
 */
#[CoversClass(ServiceRegisteredInCorrectBundleRule::class)]
class ServiceRegisteredInCorrectBundleRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $fixtureDir = __DIR__ . '/data/ServiceRegisteredInCorrectBundleRule';

        $this->analyse([$fixtureDir . '/trigger.php'], [
            [
                'Service "Shopware\Core\Framework\Example\MissingFromCore" is registered in Storefront but its effective class "Shopware\Core\Framework\Example\MissingFromCore" belongs to Core (src/Storefront/DependencyInjection/services.xml:8). Register it in a Core DependencyInjection file instead.',
                1,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/ServiceRegisteredInCorrectBundleRule';

        return new ServiceRegisteredInCorrectBundleRule($fixtureDir, $fixtureDir . '/trigger.php');
    }
}
