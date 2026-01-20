<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\Framework\Util\Backtrace\Frame;

/**
 * @internal
 */
#[CoversClass(BacktraceCollector::class)]
class BacktraceCollectorTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $frames
     */
    #[DataProvider('provideTestData')]
    public function testGetFirstFrame(
        array $frames,
        callable $skipFrame,
        ?Frame $expected
    ): void {
        $collector = (new class($frames) extends BacktraceCollector {
            /**
             * @param list<array<string, mixed>> $frames
             */
            public function __construct(private readonly array $frames)
            {
            }

            /**
             * @return list<array<string, mixed>>
             */
            protected function collectDebugBacktrace(): array
            {
                return $this->frames;
            }
        });

        static::assertEquals(
            $expected,
            $collector->getFirstFrame($skipFrame)
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideTestData(): iterable
    {
        yield 'returns first non-skipped frame' => [
            'frames' => [
                ['class' => 'Foo', 'function' => 'a'],
                ['class' => 'Bar', 'function' => 'b'],
            ],
            'skipFrame' => static fn (array $frame): bool => false,
            'expected' => new Frame('Foo', 'a'),
        ];

        yield 'skips first frame' => [
            'frames' => [
                ['class' => 'Foo', 'function' => 'a'],
                ['class' => 'Bar', 'function' => 'b'],
            ],
            'skipFrame' => static fn (array $frame): bool => $frame['class'] === 'Foo',
            'expected' => new Frame('Bar', 'b'),
        ];

        yield 'returns null when all frames are skipped' => [
            'frames' => [
                ['class' => 'Foo', 'function' => 'a'],
                ['class' => 'Bar', 'function' => 'b'],
            ],
            'skipFrame' => static fn (): bool => true,
            'expected' => null,
        ];

        yield 'empty backtrace returns null' => [
            'frames' => [],
            'skipFrame' => static fn (): bool => false,
            'expected' => null,
        ];
    }
}
