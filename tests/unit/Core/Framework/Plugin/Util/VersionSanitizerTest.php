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
     * @return list<array{string, string}>
     */
    public static function versionProvider(): array
    {
        return [
            ['1.2.3.4', '1.2.3'],
            ['1.2.3.4-RC1', '1.2.3-RC1'],
            ['1.22.333.4444-alpha', '1.22.333-alpha'],
        ];
    }
}
