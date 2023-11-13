<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Shopware\Core\Framework\Adapter\Asset\PrefixVersionStrategy;

/**
 * @internal
 */
class PrefixVersionStrategyTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testPathGetsPrefixed(string $prefix, string $fileName, string $returnPath, string $expected): void
    {
        $orgVersion = $this->createMock(FlysystemLastModifiedVersionStrategy::class);
        $orgVersion->method('applyVersion')->with(rtrim($prefix, '/') . '/' . ltrim($fileName, '/'))->willReturn($returnPath);

        $prefixVersion = new PrefixVersionStrategy(
            $prefix,
            $orgVersion
        );

        static::assertSame($expected, $prefixVersion->getVersion($fileName));
    }

    public static function dataProvider(): iterable
    {
        yield 'One file' => [
            'prefix', // Prefix
            'test.txt', // File name
            'prefix/test.txt?123', // Return path
            'test.txt?123', // Expected end path
        ];

        yield 'Sub folder' => [
            'prefix', // Prefix
            'foo/test.txt', // File name
            'prefix/foo/test.txt?123', // Return path
            'foo/test.txt?123', // Expected end path
        ];

        yield 'Prefix contains slash' => [
            'prefix/', // Prefix
            'foo/test.txt', // File name
            'prefix/foo/test.txt?123', // Return path
            'foo/test.txt?123', // Expected end path
        ];

        yield 'Filename contains slash' => [
            'prefix', // Prefix
            '/foo/test.txt', // File name
            'prefix/foo/test.txt?123', // Return path
            '/foo/test.txt?123', // Expected end path
        ];
    }
}
