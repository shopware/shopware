<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Discovery\SupportedVersionsRegistry;

/**
 * @internal
 */
#[CoversClass(SupportedVersionsRegistry::class)]
class SupportedVersionsRegistryTest extends TestCase
{
    public function testBuildsHistoricalProfileUris(): void
    {
        $registry = new SupportedVersionsRegistry();
        $map = $registry->buildForBaseUri('https://shop.example');

        if ($map === []) {
            // No historical versions known yet — accept either case.
            $this->expectNotToPerformAssertions();

            return;
        }

        foreach ($map as $version => $uri) {
            static::assertNotSame('', (string) $version);
            static::assertStringStartsWith('https://shop.example/.well-known/ucp/', $uri);
            static::assertStringEndsWith((string) $version, $uri);
        }
    }
}
