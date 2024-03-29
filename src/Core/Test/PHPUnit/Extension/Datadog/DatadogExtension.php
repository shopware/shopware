<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Gateway\DatadogGateway;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFailedSubscriber;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFinishedSubscriber;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestPreparedSubscriber;
use Shopware\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestRunnerExecutionFinishedSubscriber;

/**
 * @internal
 */
#[Package('core')]
class DatadogExtension implements Extension
{
    public const THRESHOLD_IN_SECONDS = 2;

    public const GATEWAY_URL = 'https://http-intake.logs.datadoghq.eu/v1/input';

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $timeKeeper = new TimeKeeper();
        $failedTests = new DatadogPayloadCollection();
        $slowTests = new DatadogPayloadCollection();

        $facade->registerSubscribers(
            new TestPreparedSubscriber($timeKeeper),
            new TestFailedSubscriber($timeKeeper, $failedTests),
            new TestFinishedSubscriber($timeKeeper, $slowTests),
            new TestRunnerExecutionFinishedSubscriber(
                $failedTests,
                $slowTests,
                new DatadogGateway(self::GATEWAY_URL)
            ),
        );
    }

    private function isEnabled(): bool
    {
        return isset($_SERVER['DATADOG_API_KEY'], $_SERVER['CI_COMMIT_REF_NAME']) && $_SERVER['CI_COMMIT_REF_NAME'] === 'trunk';
    }
}
