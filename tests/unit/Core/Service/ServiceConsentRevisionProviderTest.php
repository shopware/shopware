<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\ServiceConsentRevisionProvider;
use Shopware\Core\Service\ServiceRegistry\Client;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @phpstan-import-type ServiceConsentMetadata from ServiceConsentRevisionProvider
 *
 * @internal
 */
#[CoversClass(ServiceConsentRevisionProvider::class)]
class ServiceConsentRevisionProviderTest extends TestCase
{
    public function testGetMetadataFetchesRevisionsFromClient(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('fetchConsentRevisions')
            ->with('de-DE')
            ->willReturn(self::metadata());

        $provider = new ServiceConsentRevisionProvider($client, new ArrayAdapter());

        static::assertSame(self::metadata(), $provider->getMetadata('de-DE'));
    }

    public function testGetMetadataIsCachedPerLocale(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->exactly(2))
            ->method('fetchConsentRevisions')
            ->willReturnCallback(static fn (string $locale) => self::metadata($locale));

        $provider = new ServiceConsentRevisionProvider($client, new ArrayAdapter());

        static::assertSame(self::metadata('de-DE'), $provider->getMetadata('de-DE'));
        static::assertSame(self::metadata('de-DE'), $provider->getMetadata('de-DE'));
        static::assertSame(self::metadata('en-GB'), $provider->getMetadata('en-GB'));
    }

    public function testGetMetadataFallsBackToDefaultLocaleForBlankLocale(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('fetchConsentRevisions')
            ->with(ServiceConsentRevisionProvider::DEFAULT_LOCALE)
            ->willReturn(self::metadata());

        $provider = new ServiceConsentRevisionProvider($client, new ArrayAdapter());

        $provider->getMetadata('   ');
    }

    public function testLocaleIsTrimmed(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('fetchConsentRevisions')
            ->with('de-DE')
            ->willReturn(self::metadata());

        $provider = new ServiceConsentRevisionProvider($client, new ArrayAdapter());

        $provider->getMetadata(' de-DE ');
    }

    public function testGetLatestRevisionUsesDefaultLocale(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('fetchConsentRevisions')
            ->with(ServiceConsentRevisionProvider::DEFAULT_LOCALE)
            ->willReturn(self::metadata());

        $provider = new ServiceConsentRevisionProvider($client, new ArrayAdapter());

        static::assertSame('2026-05-05', $provider->getLatestRevision());
    }

    /**
     * @return ServiceConsentMetadata
     */
    private static function metadata(string $locale = 'en-GB'): array
    {
        return [
            'latest-revision' => '2026-05-05',
            'available-revisions' => [
                [
                    'revision' => '2026-05-05',
                    'links' => [
                        'feedback-url' => 'https://example.com/' . $locale . '/feedback',
                        'docs-url' => 'https://example.com/' . $locale . '/docs',
                        'tos-url' => 'https://example.com/' . $locale . '/tos',
                    ],
                ],
            ],
        ];
    }
}
