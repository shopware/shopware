<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\LanguageStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\LanguageAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\LanguageAwareEvent;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\NonLanguageAwareEvent;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LanguageStorer::class)]
class LanguageStorerTest extends TestCase
{
    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $expected
     */
    #[DataProvider('storeDataProvider')]
    public function testStore(FlowEventAware $event, array $stored, array $expected): void
    {
        $storer = new LanguageStorer();
        $stored = $storer->store($event, $stored);

        static::assertSame($expected, $stored);
    }

    public static function storeDataProvider(): \Generator
    {
        $languageId = Uuid::randomHex();

        yield 'store null' => [
            'event' => new LanguageAwareEvent(null),
            'stored' => [],
            'expected' => [LanguageAware::LANGUAGE_ID => null],
        ];

        yield 'store id' => [
            'event' => new LanguageAwareEvent($languageId),
            'stored' => [],
            'expected' => [LanguageAware::LANGUAGE_ID => $languageId],
        ];

        yield 'store existing' => [
            'event' => new LanguageAwareEvent($languageId),
            'stored' => ['message' => 'hi'],
            'expected' => ['message' => 'hi', LanguageAware::LANGUAGE_ID => $languageId],
        ];

        yield 'store null with existing' => [
            'event' => new LanguageAwareEvent(null),
            'stored' => ['message' => 'hi'],
            'expected' => ['message' => 'hi', LanguageAware::LANGUAGE_ID => null],
        ];

        yield 'store non language aware' => [
            'event' => new NonLanguageAwareEvent(),
            'stored' => [],
            'expected' => [],
        ];

        yield 'store non language aware with existing' => [
            'event' => new NonLanguageAwareEvent(),
            'stored' => ['message' => 'hi'],
            'expected' => ['message' => 'hi'],
        ];

        $languageId2 = Uuid::randomHex();

        yield 'store overwrite' => [
            'event' => new LanguageAwareEvent($languageId2),
            'stored' => ['message' => 'hi', LanguageAware::LANGUAGE_ID => $languageId],
            'expected' => ['message' => 'hi', LanguageAware::LANGUAGE_ID => $languageId2],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('restoreDataProvider')]
    public function testRestore(StorableFlow $flow, array $expected): void
    {
        $storer = new LanguageStorer();
        $storer->restore($flow);

        static::assertSame($expected, $flow->data());
    }

    public static function restoreDataProvider(): \Generator
    {
        $languageId = Uuid::randomHex();

        yield 'restore empty' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), []),
            'expected' => [],
        ];

        yield 'restore id' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), [
                LanguageAware::LANGUAGE_ID => $languageId,
            ]),
            'expected' => [LanguageAware::LANGUAGE_ID => $languageId],
        ];

        yield 'restore null' => [
            'flow' => new StorableFlow('foo', Context::createDefaultContext(), [
                LanguageAware::LANGUAGE_ID => null,
            ]),
            'expected' => [LanguageAware::LANGUAGE_ID => null],
        ];
    }
}
