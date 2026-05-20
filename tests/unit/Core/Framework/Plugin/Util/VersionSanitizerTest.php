<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;

/**
 * @internal
 */
#[CoversClass(VersionSanitizer::class)]
class VersionSanitizerTest extends TestCase
{
    #[DataProvider('versionProvider')]
    public function testSanitizePluginVersion(string $version, string $expectedVersion): void
    {
        $sanitizedVersion = (new VersionSanitizer())->sanitizePluginVersion($version);

        static::assertSame($expectedVersion, $sanitizedVersion);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function versionProvider(): iterable
    {
        yield 'version 1 2 3 4 1 2 3' => ['1.2.3.4', '1.2.3'];
        yield 'version 1 2 3 4 rc1 1 2 3 rc1' => ['1.2.3.4-RC1', '1.2.3-RC1'];
        yield 'version 1 22 333 4444 alpha 1 22 333 alpha' => ['1.22.333.4444-alpha', '1.22.333-alpha'];
    }
}
