<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\License;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\License\LicenseFetcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LicenseFetcher::class)]
class LicenseFetcherTest extends TestCase
{
    private const DATA = [
        'https://tos.de' => '<h1>Deutsch</h1>',
        'https://tos.en' => '<h1>English</h1>',
    ];

    #[DataProvider('licenseDataProvider')]
    public function testFetch(string $locale, string $expectedUrl, string $expectedContent): void
    {
        $guzzleHandler = new MockHandler([new Response(200, [], self::DATA[$expectedUrl])]);
        $guzzle = new Client(['handler' => $guzzleHandler]);

        $fetcher = new LicenseFetcher($guzzle, [
            'de' => 'https://tos.de',
            'en' => 'https://tos.en',
        ]);

        $license = $fetcher->fetch(new Request([], [], ['_locale' => $locale]));

        static::assertSame($expectedContent, $license);

        $request = $guzzleHandler->getLastRequest();
        static::assertNotNull($request);

        static::assertSame($expectedUrl, (string) $request->getUri());
    }

    public static function licenseDataProvider(): \Generator
    {
        yield 'german license' => [
            'de',
            'https://tos.de',
            '<h1>Deutsch</h1>',
        ];

        yield 'english license' => [
            'en',
            'https://tos.en',
            '<h1>English</h1>',
        ];

        yield 'unknown locale falls back to english' => [
            'es',
            'https://tos.en',
            '<h1>English</h1>',
        ];
    }
}
