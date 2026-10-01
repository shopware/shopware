<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\Code\TestCollection;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestSuite\TestSuiteWithName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;
use Shopware\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionStartedSubscriber;
use Shopware\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MarkExecutionStartedSubscriber::class)]
class MarkExecutionStartedSubscriberTest extends TestCase
{
    private bool $previousState;

    protected function setUp(): void
    {
        $this->previousState = CompletionGuard::$executionStarted;
    }

    protected function tearDown(): void
    {
        // the guard is armed for the very suite running this test; restore whatever state it had
        CompletionGuard::$executionStarted = $this->previousState;
    }

    public function testNotifyMarksExecutionAsStarted(): void
    {
        CompletionGuard::$executionStarted = false;

        (new MarkExecutionStartedSubscriber())->notify($this->buildEvent());

        static::assertTrue(CompletionGuard::$executionStarted);
    }

    private function buildEvent(): ExecutionStarted
    {
        return new ExecutionStarted(
            TelemetryInfoFactory::create(),
            new TestSuiteWithName('suite', 0, TestCollection::fromArray([])),
        );
    }
}
