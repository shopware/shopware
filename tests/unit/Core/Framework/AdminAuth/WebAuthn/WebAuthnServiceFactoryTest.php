<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\WebAuthn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\WebAuthn\WebAuthnServiceFactory;

/**
 * @internal
 */
#[CoversClass(WebAuthnServiceFactory::class)]
class WebAuthnServiceFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function appUrlProvider(): iterable
    {
        yield 'https host' => ['https://admin.example.com', 'admin.example.com'];
        yield 'http localhost with port' => ['http://localhost:8000', 'localhost'];
        yield 'http localhost tld' => ['http://sw-trunk.localhost', 'sw-trunk.localhost'];
        yield 'https with path' => ['https://shop.example.org/admin', 'shop.example.org'];
        yield 'https default port' => ['https://example.com:443', 'example.com'];
        yield 'empty url falls back to localhost' => ['', 'localhost'];
    }

    #[DataProvider('appUrlProvider')]
    public function testRpIdIsHostOfAppUrl(string $appUrl, string $expectedHost): void
    {
        $service = (new WebAuthnServiceFactory($appUrl))->create();

        $options = $service->createRegistrationOptions('uid', 'user', 'User');

        static::assertSame($expectedHost, $options->rp->id);
    }

    public function testRpNameDefaultsToShopwareAdmin(): void
    {
        static::assertSame('Shopware Admin', $this->serializedRpName(new WebAuthnServiceFactory('https://admin.example.com')));
    }

    public function testRpNameCanBeOverridden(): void
    {
        static::assertSame(
            'My Custom RP',
            $this->serializedRpName(new WebAuthnServiceFactory('https://admin.example.com', 'My Custom RP'))
        );
    }

    /**
     * The rp name lives on a deprecated shared property, so it is read from the wire format.
     */
    private function serializedRpName(WebAuthnServiceFactory $factory): string
    {
        $service = $factory->create();
        $options = $service->createRegistrationOptions('uid', 'user', 'User');

        $json = json_decode($service->serializeOptions($options), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($json);
        static::assertIsString($json['rp']['name']);

        return $json['rp']['name'];
    }
}
