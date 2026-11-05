<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\DefaultParameterMap;
use PHPStan\Symfony\Parameter;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoUnconfiguredMetricAllowed;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoUnconfiguredMetricAllowed>
 */
#[Package('framework')]
class NoUnconfiguredMetricAllowedTest extends RuleTestCase
{
    public function testReportsUnconfiguredMetricNamesOnBothValueObjects(): void
    {
        // dynamic names (line 22 of the fixture) are out of the rule's scope and must not be reported
        $this->analyse(
            [__DIR__ . '/data/NoUnconfiguredMetricAllowed/MetricEmitter.php'],
            [
                ['Metric "unconfigured.metric" is not configured', 17],
                ['Metric "unconfigured.named.metric" is not configured', 18],
                ['Metric "unconfigured.duration" is not configured', 20],
                ['Metric "unconfigured.named.duration" is not configured', 21],
            ],
        );
    }

    protected function getRule(): Rule
    {
        /** @phpstan-ignore phpstanApi.constructor */
        return new NoUnconfiguredMetricAllowed(new DefaultParameterMap([
            /** @phpstan-ignore phpstanApi.constructor */
            'shopware.telemetry.metrics.definitions' => new Parameter('shopware.telemetry.metrics.definitions', [
                'configured.metric' => ['type' => 'counter'],
                'configured.duration' => ['type' => 'histogram'],
            ]),
        ]));
    }
}
