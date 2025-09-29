<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(HttpCacheKeyGenerator::class)]
#[CoversClass(HttpCacheKeyEvent::class)]
#[Group('cache')]
class HttpCacheKeyGeneratorTest extends TestCase
{
    use EventDispatcherBehaviour;

    private HttpCacheKeyGenerator $cacheKeyGenerator;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->cacheKeyGenerator = new HttpCacheKeyGenerator('foo', $this->eventDispatcher, ['_ga']);
    }

    #[DataProvider('differentKeyProvider')]
    public function testDifferentCacheKey(Request $requestA, Request $requestB): void
    {
        static::assertNotSame(
            $this->cacheKeyGenerator->generate($requestA),
            $this->cacheKeyGenerator->generate($requestB),
        );
    }

    #[DataProvider('sameKeyProvider')]
    public function testSameCacheKey(Request $requestA, Request $requestB): void
    {
        static::assertSame(
            $this->cacheKeyGenerator->generate($requestA),
            $this->cacheKeyGenerator->generate($requestB),
        );
    }

    public function testCacheKeyStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $request = Request::create('https://domain.com/method');
        $firstHash = $this->cacheKeyGenerator->generate($request);

        $this->addEventListener($this->eventDispatcher, HttpCacheKeyEvent::class, static function (HttpCacheKeyEvent $event): void {
            $uri = $event->get('uri');
            self::assertIsString($uri);
            $event->remove('uri');
            $event->add('uri', $uri);
        });

        $secondHash = $this->cacheKeyGenerator->generate($request);
        static::assertSame($firstHash, $secondHash);
    }

    public static function sameKeyProvider(): \Generator
    {
        yield 'same Url with same get Parameter in different order' => [
            Request::create('https://domain.com/method?limit=1&order=ASC'),
            Request::create('https://domain.com/method?order=ASC&limit=1'),
        ];

        yield 'same URL with excluded parameter from ignore list' => [
            Request::create('https://domain.com/method'),
            Request::create('https://domain.com/method?_ga=1'),
        ];

        yield 'same Url with lost question mark' => [
            Request::create('https://domain.com/method?'),
            Request::create('https://domain.com/method'),
        ];
    }

    public static function differentKeyProvider(): \Generator
    {
        yield 'Urls with different actions' => [
            Request::create('https://domain.com/actionA'),
            Request::create('https://domain.com/actionB'),
        ];

        yield 'Urls with same Action, but different Get Parameters' => [
            Request::create('https://domain.com/actionA?limit=1'),
            Request::create('https://domain.com/actionA?limit=2'),
        ];
    }
}
