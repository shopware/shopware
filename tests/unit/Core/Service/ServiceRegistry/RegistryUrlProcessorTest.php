<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ServiceRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceRegistry\RegistryUrlProcessor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RegistryUrlProcessor::class)]
class RegistryUrlProcessorTest extends TestCase
{
    private const DEFAULT_URL = 'https://registry.services.shopware.io';

    #[DataProvider('trustedUrlProvider')]
    public function testUrlOnATrustedDomainIsUsed(string $url): void
    {
        static::assertSame($url, $this->process($url, ['shopware.io']));
    }

    #[DataProvider('untrustedUrlProvider')]
    public function testUrlOutsideOfTheTrustedDomainsFallsBackToTheDefaultUrl(string $url): void
    {
        static::assertSame(self::DEFAULT_URL, $this->process($url, ['shopware.io']));
    }

    #[DataProvider('untrustedUrlProvider')]
    public function testAnyUrlIsUsedWhenNoDomainIsTrusted(string $url): void
    {
        static::assertSame($url, $this->process($url, []));
    }

    public function testGetProvidedTypes(): void
    {
        static::assertSame(
            ['service-registry-url' => 'string'],
            RegistryUrlProcessor::getProvidedTypes()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function trustedUrlProvider(): iterable
    {
        yield 'production registry' => ['https://registry.services.shopware.io'];
        yield 'staging registry' => ['https://registry.staging-services.shopware.io'];
        yield 'registry with a path' => ['https://registry.services.shopware.io/api'];
        yield 'trusted domain itself' => ['https://shopware.io'];
        yield 'uppercase host' => ['https://REGISTRY.SERVICES.SHOPWARE.IO'];
        yield 'host with a trailing dot' => ['https://registry.services.shopware.io.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untrustedUrlProvider(): iterable
    {
        yield 'foreign host' => ['https://registry.example.com'];
        yield 'host ending in the trusted domain' => ['https://notshopware.io'];
        yield 'trusted domain as a subdomain of a foreign host' => ['https://registry.services.shopware.io.example.com'];
        yield 'trusted domain in the user info' => ['https://registry.services.shopware.io@example.com'];
        yield 'trusted domain in the path' => ['https://example.com/registry.services.shopware.io'];
        yield 'local registry' => ['http://host.docker.internal:8123'];
        yield 'host without a scheme' => ['registry.services.shopware.io'];
        yield 'empty value' => [''];
    }

    /**
     * @param list<string> $trustedDomains
     */
    private function process(string $url, array $trustedDomains): string
    {
        $processor = new RegistryUrlProcessor(self::DEFAULT_URL, $trustedDomains);

        return $processor->getEnv('service-registry-url', 'SERVICE_REGISTRY_URL', static fn () => $url);
    }
}
