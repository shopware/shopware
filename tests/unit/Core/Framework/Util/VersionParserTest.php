<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\VersionParser;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[CoversClass(VersionParser::class)]
class VersionParserTest extends TestCase
{
    #[DataProvider('provideVersions')]
    public function testParseShopwareVersion(string $unparsedVersion, string $parsedVersion, string $parsedRevision): void
    {
        $version = VersionParser::parseShopwareVersion($unparsedVersion);

        static::assertEquals($parsedVersion, $version['version']);
        static::assertEquals($parsedRevision, $version['revision']);
    }

    /**
     * @return string[][]
     */
    public static function provideVersions(): array
    {
        return [
            [
                '6.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.1.1.12-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.10.10.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.10.10.x-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.3.1.x-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.x-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                'v6.3.1.1-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                '6.3.1.1-dev',
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                '12.1.1.12-dev@764cf86c6e8f826b9f125c28fa91f89ad43bc279',
                Kernel::SHOPWARE_FALLBACK_VERSION,
                '764cf86c6e8f826b9f125c28fa91f89ad43bc279',
            ],
            [
                'v6.3.1.1',
                Kernel::SHOPWARE_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                '6.2.1',
                Kernel::SHOPWARE_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                'foobar',
                Kernel::SHOPWARE_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
            [
                '1010806',
                Kernel::SHOPWARE_FALLBACK_VERSION,
                '00000000000000000000000000000000',
            ],
        ];
    }
}
