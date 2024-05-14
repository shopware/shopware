<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RemoveEventListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(RemoveEventListener::class)]
class RemoveEventListenerTest extends TestCase
{
    /**
     * @param array<array{event:string, method:string}> $listeners
     * @param string[] $remove
     * @param array<array{event:string, method:string}> $expected
     */
    #[DataProvider('removeProvider')]
    public function testRemove(
        array $listeners,
        array $remove,
        array $expected
    ): void {
        $builder = new ContainerBuilder();

        $definition = new Definition('class-string');
        foreach ($listeners as $listener) {
            $definition->addTag('kernel.event_listener', $listener);
        }

        $builder->addDefinitions(['class-string' => $definition]);

        RemoveEventListener::remove($builder, 'class-string', [$remove]);

        if ($expected === []) {
            static::assertFalse($definition->hasTag('kernel.event_listener'));

            return;
        }

        static::assertTrue($definition->hasTag('kernel.event_listener'));

        $current = $definition->getTag('kernel.event_listener');

        static::assertEquals($expected, $current, \print_r($current, true));
    }

    public static function removeProvider(): \Generator
    {
        yield 'Test without having tag' => [
            [],
            ['event' => 'event-1', 'method' => 'method-1'],
            [],
        ];

        yield 'Test remove last tag' => [
            [
                ['event' => 'event-1', 'method' => 'method-1'],
            ],
            ['event' => 'event-1', 'method' => 'method-1'],
            [],
        ];

        yield 'Test remove will keep other' => [
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
            ['event' => 'event-1', 'method' => 'method-1'],
            [
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
        ];

        yield 'Test remove will keep other - with none-associative array' => [
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
            ['event-1', 'method-1'],
            [
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
        ];

        yield 'Test keep events with unknown event' => [
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
            ['event' => 'unknown', 'method' => 'method-1'],
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
        ];

        yield 'Test keep events with unknown method' => [
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
            ['event' => 'event-1', 'method' => 'unknown'],
            [
                ['event' => 'event-1', 'method' => 'method-1'],
                ['event' => 'event-2', 'method' => 'method-1'],
            ],
        ];
    }
}
