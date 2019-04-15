<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;

class VersionSanitizerTest extends TestCase
{
    /**
     * @dataProvider versionProvider
     */
    public function testSanitizePluginVersion(string $version, string $expectedVersion): void
    {
        $sanitizedVersion = (new VersionSanitizer())->sanitizePluginVersion($version);

        static::assertSame($expectedVersion, $sanitizedVersion);
    }

    public function versionProvider(): array
    {
        return [
            ['1.2.3.4', '1.2.3'],
            ['1.2.3.4-RC1', '1.2.3-RC1'],
            ['1.22.333.4444-alpha', '1.22.333-alpha'],
        ];
    }
}
