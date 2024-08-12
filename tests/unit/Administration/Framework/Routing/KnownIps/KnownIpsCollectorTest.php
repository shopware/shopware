<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Routing\KnownIps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(KnownIpsCollector::class)]
class KnownIpsCollectorTest extends TestCase
{
    public function testSuggestionsForIpv4(): void
    {
        $suggestions = (new KnownIpsCollector())->collectIps(new Request(server: ['REMOTE_ADDR' => '127.0.0.1']));

        static::assertSame([
            '127.0.0.1' => 'global.sw-multi-tag-ip-select.knownIps.you',
        ], $suggestions);
    }

    public function testSuggestionsForIpv6(): void
    {
        $suggestions = (new KnownIpsCollector())->collectIps(new Request(server: ['REMOTE_ADDR' => '2001:0db8:0123:4567:89ab:cdef:1234:5678']));

        static::assertEqualsCanonicalizing([
            '2001:0db8:0123:4567:89ab:cdef:1234:5678' => 'global.sw-multi-tag-ip-select.knownIps.you',
            '2001:db8:123:4567::/64' => 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block64',
            '2001:db8:123:4500::/56' => 'global.sw-multi-tag-ip-select.knownIps.youIPv6Block56',
        ], $suggestions);
    }

    public function testCollectIpsWithNoIpGiven(): void
    {
        $als = new KnownIpsCollector();
        static::assertEquals([], $als->collectIps(new Request(server: [''])));
    }
}
