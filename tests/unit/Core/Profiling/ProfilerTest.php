<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Integration\ProfilerInterface;
use Shopware\Core\Profiling\Integration\Stopwatch;
use Shopware\Core\Profiling\Profiler;
use Symfony\Component\Stopwatch\Stopwatch as SymfonyStopwatch;

/**
 * @internal
 */
#[CoversClass(Profiler::class)]
class ProfilerTest extends TestCase
{
    public function testStartAndStopDoNotCauseCleanupException(): void
    {
        $symfonyStopwatch = new SymfonyStopwatch();
        $stopwatch = new Stopwatch($symfonyStopwatch);

        $profilers = new \ArrayIterator(['stopwatch' => $stopwatch]);
        new Profiler($profilers, ['stopwatch']);

        Profiler::start('test', 'category', []);
        Profiler::stop('test');

        $exception = null;
        try {
            Profiler::cleanup();
        } catch (\LogicException $e) {
            $exception = $e;
        }

        static::assertNull($exception, $exception?->getMessage() ?? '');
    }

    public function testCleanupStopsAllOpenTraces(): void
    {
        $profilerMock = $this->createMock(ProfilerInterface::class);
        $profilerMock->expects($this->exactly(3))->method('start');
        $profilerMock->expects($this->exactly(3))->method('stop')
            ->willReturnCallback(static function (string $name): void {
                static::assertContains($name, ['trace1', 'trace2', 'trace3']);
            });

        new Profiler(new \ArrayIterator(['test' => $profilerMock]), ['test']);

        Profiler::start('trace1', 'category', []);
        Profiler::start('trace2', 'category', []);
        Profiler::start('trace3', 'category', []);

        Profiler::cleanup();
    }

    public function testTraceWithoutTags(): void
    {
        $this->createProfiler('test-trace', 'shopware', []);

        $result = Profiler::trace('test-trace', static fn () => 'test-result');

        static::assertSame('test-result', $result);
    }

    public function testTraceWithTags(): void
    {
        $this->createProfiler('test-trace', 'shopware', ['key1' => 'value1', 'key2' => 'value2']);

        Profiler::trace('test-trace', static fn () => null, 'shopware', ['key1' => 'value1', 'key2' => 'value2']);
    }

    public function testTraceWithGlobalTags(): void
    {
        $this->createProfiler('test-trace', 'shopware', ['global' => 'tag', 'local' => 'tag']);

        Profiler::addTag('global', 'tag');
        Profiler::trace('test-trace', static fn () => null, 'shopware', ['local' => 'tag']);
    }

    public function testTraceStopsProfilerEvenOnException(): void
    {
        $profilerMock = $this->createMock(ProfilerInterface::class);
        $profilerMock->expects($this->once())->method('start')->with('test-trace', 'shopware', []);
        $stopCalled = false;

        $profilerMock->expects($this->once())->method('stop')->with('test-trace')
            ->willReturnCallback(static function () use (&$stopCalled): void {
                $stopCalled = true;
            });

        new Profiler(new \ArrayIterator(['test' => $profilerMock]), ['test']);

        $this->expectExceptionObject(new \RuntimeException('Test exception'));

        try {
            Profiler::trace('test-trace', static fn () => throw new \RuntimeException('Test exception'));
        } finally {
            static::assertTrue($stopCalled, 'Profiler stop() should have been called even with exception');
        }
    }

    public function testTraceWithMultipleProfilers(): void
    {
        $profilerMock1 = $this->createMock(ProfilerInterface::class);
        $profilerMock1->expects($this->once())->method('start')->with('test-trace', 'shopware', []);
        $profilerMock1->expects($this->once())->method('stop')->with('test-trace');

        $profilerMock2 = $this->createMock(ProfilerInterface::class);
        $profilerMock2->expects($this->once())->method('start')->with('test-trace', 'shopware', []);
        $profilerMock2->expects($this->once())->method('stop')->with('test-trace');

        new Profiler(new \ArrayIterator(['profiler1' => $profilerMock1, 'profiler2' => $profilerMock2]), ['profiler1', 'profiler2']);

        Profiler::trace('test-trace', static fn () => 'result');
    }

    public function testAddTag(): void
    {
        $this->createProfiler('test-trace', 'shopware', ['tag1' => 'value1', 'tag2' => 'value2']);

        Profiler::addTag('tag1', 'value1');
        Profiler::addTag('tag2', 'value2');
        Profiler::trace('test-trace', static fn () => null);
    }

    public function testRemoveTag(): void
    {
        $this->createProfiler('test-trace', 'shopware', ['tag1' => 'value1']);

        Profiler::addTag('tag1', 'value1');
        Profiler::addTag('tag2', 'value2');
        Profiler::removeTag('tag2');
        Profiler::trace('test-trace', static fn () => null);
    }

    public function testLocalTagsOverrideGlobalTags(): void
    {
        $this->createProfiler('test-trace', 'shopware', ['tag1' => 'local-value', 'tag2' => 'global-value']);

        Profiler::addTag('tag1', 'global-value');
        Profiler::addTag('tag2', 'global-value');
        Profiler::trace('test-trace', static fn () => null, 'shopware', ['tag1' => 'local-value']);
    }

    /**
     * @param array<string, string> $expectedTags
     */
    private function createProfiler(string $name, string $category, array $expectedTags): ProfilerInterface
    {
        $profilerMock = $this->createMock(ProfilerInterface::class);
        $profilerMock->expects($this->once())->method('start')->with($name, $category, $expectedTags);
        $profilerMock->expects($this->once())->method('stop')->with($name);

        new Profiler(new \ArrayIterator(['test' => $profilerMock]), ['test']);

        return $profilerMock;
    }
}
