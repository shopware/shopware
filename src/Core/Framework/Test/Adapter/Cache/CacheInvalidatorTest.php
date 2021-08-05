<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * @group cache
 */
class CacheInvalidatorTest extends TestCase
{
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @dataProvider demandInvalidationProvider
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
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('scheduled_task.repository')
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

class TracedCacheAdapter extends ArrayAdapter implements TagAwareAdapterInterface
{
    private array $invalidated = [];

    public function invalidateTags(array $tags): void
    {
        $this->invalidated = array_merge($this->invalidated, $tags);
    }

    public function getInvalidated(): array
    {
        return $this->invalidated;
    }
}
