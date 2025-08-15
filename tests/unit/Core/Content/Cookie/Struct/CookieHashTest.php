<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieHash;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieHash::class)]
class CookieHashTest extends TestCase
{
    public function testConstructorAndGetter(): void
    {
        $hash = 'abc123def456';
        $struct = new CookieHash($hash);

        static::assertSame($hash, $struct->cookieHash);
    }

    public function testGetApiAlias(): void
    {
        $struct = new CookieHash('test-hash');
        static::assertSame('cookie_hash', $struct->getApiAlias());
    }

    public function testSha1Hash(): void
    {
        $hash = 'd1311937c9f21d5724151afe1ab26876bbe6e6b2'; // SHA-1 hash
        $struct = new CookieHash($hash);

        static::assertSame($hash, $struct->cookieHash);
        static::assertSame(40, \strlen($struct->cookieHash)); // SHA-1 produces 40 character hex string
        static::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $struct->cookieHash);
    }

    public function testEmptyHash(): void
    {
        $hash = '';
        $struct = new CookieHash($hash);

        static::assertSame($hash, $struct->cookieHash);
    }

    public function testJsonSerialization(): void
    {
        $hash = 'abc123def456789';
        $struct = new CookieHash($hash);

        $serialized = $struct->jsonSerialize();

        static::assertIsArray($serialized);
        static::assertArrayHasKey('cookieHash', $serialized);
        static::assertSame($hash, $serialized['cookieHash']);

        // API alias is not included in jsonSerialize, but available via getApiAlias()
        static::assertSame('cookie_hash', $struct->getApiAlias());
    }
}
