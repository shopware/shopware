<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\License;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\License\LicenseFetcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Installer\License\LicenseFetcher
 */
class LicenseFetcherTest extends TestCase
{
    private const DATA = [
        'https://tos.de' => '<h1>Deutsch</h1>',
        'https://tos.en' => '<h1>English</h1>',
    ];

    /**
     * @dataProvider licenseDataProvider
     */
    public function testFetch(string $locale, string $expectedUrl, string $expectedContent): void
    {
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects(static::once())
            ->method('get')
            ->with($expectedUrl)
            ->willReturn(new Response(200, [], self::DATA[$expectedUrl]));

        $fetcher = new LicenseFetcher($guzzle, [
            'de' => 'https://tos.de',
            'en' => 'https://tos.en',
        ]);

        $license = $fetcher->fetch(new Request([], [], ['_locale' => $locale]));

        static::assertSame($expectedContent, $license);
    }

    public function licenseDataProvider(): \Generator
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
