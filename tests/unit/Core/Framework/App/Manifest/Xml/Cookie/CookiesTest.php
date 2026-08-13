<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Cookies::class)]
class CookiesTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getCookies());
        $cookies = $manifest->getCookies()->getCookies();
        static::assertCount(2, $cookies);

        static::assertArrayNotHasKey('active_payment_methods', $cookies[0]);

        static::assertArrayHasKey('entries', $cookies[1]);
        $entries = $cookies[1]['entries'];
        static::assertIsArray($entries);
        static::assertCount(2, $entries);
        static::assertArrayNotHasKey('active_payment_methods', $entries[0]);
        static::assertSame(
            ['myPaymentMethod', 'myOtherPaymentMethod'],
            $entries[1]['active_payment_methods'] ?? null
        );
    }
}
