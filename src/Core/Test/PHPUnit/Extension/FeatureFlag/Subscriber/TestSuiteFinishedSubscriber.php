<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\Subscriber;

use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;
use PHPUnit\Event\TestSuite\TestSuiteForTestClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\FeatureFlag\SavedConfig;

/**
 * Restores the `$_SERVER` snapshot taken by {@see TestSuiteStartedSubscriber} once the class has
 * finished executing, so state does not leak between test classes.
 *
 * @internal
 */
#[Package('framework')]
class TestSuiteFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private readonly SavedConfig $savedConfig)
    {
    }

    public function notify(Finished $event): void
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
