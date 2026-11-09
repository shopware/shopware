<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Example\NoFeatureSkipInUnitTestsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 *
 * The migration suite takes its flag state from the job environment, so the guards vary there and the rule
 * must stay silent outside the enabled namespaces.
 */
class CasesOutOfScope extends TestCase
{
    public function testGuardsVaryWithTheEnvironment(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        if (Feature::isActive('v6.8.0.0')) {
            static::markTestSkipped('legacy branch only');
        }

        static::assertTrue(true);
    }
}
