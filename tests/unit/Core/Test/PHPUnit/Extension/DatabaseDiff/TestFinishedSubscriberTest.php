<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\PHPUnit\Extension\DatabaseDiff;

use PHPUnit\Event\Code\Phpt;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;
use Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff\Subscriber\TestFinishedSubscriber;
use Shopware\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TestFinishedSubscriber::class)]
class TestFinishedSubscriberTest extends TestCase
{
    public function testACleanDbStatePrintsNothing(): void
    {
        $dbState = static::createStub(DbState::class);
        $dbState->method('getDiff')->willReturn([]);

        $this->expectOutputString('');

        (new TestFinishedSubscriber($dbState))->notify($this->buildEvent());
    }

    public function testADirtyDbStatePrintsTheDiff(): void
    {
        $dbState = static::createStub(DbState::class);
        $dbState->method('getDiff')->willReturn(['product' => 3]);

        $this->expectOutputRegex('/product/');

        (new TestFinishedSubscriber($dbState))->notify($this->buildEvent());
    }

    private function buildEvent(): Finished
    {
        return new Finished(TelemetryInfoFactory::create(), new Phpt('fakeFile'), 0);
    }
}
