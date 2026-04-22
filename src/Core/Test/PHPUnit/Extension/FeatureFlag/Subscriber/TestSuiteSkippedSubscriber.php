<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber;

use PHPUnit\Event\TestSuite\Skipped;
use PHPUnit\Event\TestSuite\SkippedSubscriber;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;

/**
 * Counterpart of {@see TestSuiteFinishedSubscriber} for the skip path: when a class throws
 * `SkippedTestSuiteError` from `setUpBeforeClass`, PHPUnit emits `TestSuite\Skipped` instead of
 * `TestSuite\Finished`, so the `$_SERVER` snapshot must be restored here too.
 *
 * @internal
 */
#[Package('framework')]
class TestSuiteSkippedSubscriber implements SkippedSubscriber
{
    public function __construct(private readonly SavedConfig $savedConfig)
    {
    }

    public function notify(Skipped $event): void
    {
        if (!$event->testSuite() instanceof TestSuiteForTestClass) {
            return;
        }

        if ($this->savedConfig->classSavedServerVars === null) {
            return;
        }

        $_SERVER = $this->savedConfig->classSavedServerVars;
        $this->savedConfig->classSavedServerVars = null;
    }
}
