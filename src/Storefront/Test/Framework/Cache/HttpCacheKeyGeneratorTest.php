<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Cache\AbstractHttpCacheKeyGenerator;
use Shopware\Storefront\Framework\Cache\HttpCacheKeyGenerator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 */
class HttpCacheKeyGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    private AbstractHttpCacheKeyGenerator $cacheKeyGenerator;

    protected function setUp(): void
    {
        $this->cacheKeyGenerator = $this->getContainer()->get(HttpCacheKeyGenerator::class);
    }

    /**
     * @dataProvider differentKeyProvider
     */
    public function testDifferentCacheKey(Request $requestA, Request $requestB): void
    {
        static::assertNotSame(
            $this->cacheKeyGenerator->generate($requestA),
            $this->cacheKeyGenerator->generate($requestB),
        );
    }

    /**
     * @dataProvider sameKeyProvider
     */
    public function testSameCacheKey(Request $requestA, Request $requestB): void
    {
        static::assertSame(
            $this->cacheKeyGenerator->generate($requestA),
            $this->cacheKeyGenerator->generate($requestB),
        );
    }

    public function sameKeyProvider(): \Generator
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

    public function differentKeyProvider(): \Generator
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
