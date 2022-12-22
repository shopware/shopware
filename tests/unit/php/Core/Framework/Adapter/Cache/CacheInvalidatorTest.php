<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 * @group cache
 *
 * @covers \Shopware\Core\Framework\Adapter\Cache\CacheInvalidator
 */
class CacheInvalidatorTest extends TestCase
{
    /**
     * @dataProvider demandInvalidationProvider
     *
     * @param array<string, string> $logs
     * @param list<string> $expected
     */
    public function testDemandValidation(array $logs, ?\DateTime $time, int $delay, array $expected): void
    {
        $adapter = new TracedCacheAdapter();

        $item = CacheCompressor::compress(new CacheItem(), $logs);

        if ($delay > 0 && $time !== null) {
            $time->modify(sprintf('-%s second', $delay));
        }

        $storage = $this->createMock(TagAwareAdapter::class);
        $storage->expects(static::once())
            ->method('getItem')
            ->willReturn($item);

        $logger = new CacheInvalidator(
            $delay,
            150,
            [$adapter],
            $storage,
            new EventDispatcher(),
            $this->createMock(EntityRepository::class)
        );

        $logger->invalidateExpired($time);

        $invalidated = $adapter->getInvalidated();

        static::assertCount(\count($expected), $invalidated);
        foreach ($expected as $key) {
            static::assertContains($key, $invalidated);
        }
    }

    public function demandInvalidationProvider(): \Generator
    {
        yield 'Test one key match' => [
            [
                'key-1' => '2021-03-03 13:00:00',
                'key-2' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-1'],
        ];

        yield 'Test invalidate all' => [
            [
                'key-1' => '2021-03-03 13:00:00',
                'key-2' => '2021-03-03 13:10:00',
            ],
            null,
            60,
            ['key-1', 'key-2'],
        ];

        yield 'Test multiple keys match' => [
            [
                'key-1' => '2021-03-03 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-1', 'key-2'],
        ];

        yield 'Test different day' => [
            [
                'key-1' => '2021-03-02 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-1', 'key-2'],
        ];

        yield 'Test different day #2' => [
            [
                'key-1' => '2021-03-04 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-2'],
        ];

        yield 'Test different month' => [
            [
                'key-1' => '2021-02-03 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-1', 'key-2'],
        ];

        yield 'Test different month #2' => [
            [
                'key-1' => '2021-04-03 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-2'],
        ];

        yield 'Test different year' => [
            [
                'key-1' => '2020-03-03 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-1', 'key-2'],
        ];

        yield 'Test different year #2' => [
            [
                'key-1' => '2022-03-03 13:00:00',
                'key-2' => '2021-03-03 13:09:00',
                'key-3' => '2021-03-03 13:10:00',
            ],
            new \DateTime('2021-03-03 13:10:00', new \DateTimeZone('UTC')),
            60,
            ['key-2'],
        ];
    }
}

/**
 * @internal
 */
class TracedCacheAdapter extends ArrayAdapter implements TagAwareAdapterInterface
{
    /**
     * @var list<string>
     */
    private array $invalidated = [];

    /**
     * @param list<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        $this->invalidated = array_merge($this->invalidated, $tags);

        return true;
    }

    /**
     * @return list<string>
     */
    public function getInvalidated(): array
    {
        return $this->invalidated;
    }
}
