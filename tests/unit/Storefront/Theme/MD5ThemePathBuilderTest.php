<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\MD5ThemePathBuilder;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\MD5ThemePathBuilder
 */
class MD5ThemePathBuilderTest extends TestCase
{
    public function testAssemblePath(): void
    {
        $builder = new MD5ThemePathBuilder();
        $path = $builder->assemblePath('salesChannelId', 'themeId');

        static::assertEquals('5c7a1cfde64c7f4533daa5a0c06c0a39', $path);
    }

    public function testGenerateNewPathEqualsAssemblePath(): void
    {
        $builder = new MD5ThemePathBuilder();
        $path = $builder->generateNewPath('salesChannelId', 'themeId', 'foo');

        static::assertEquals($builder->assemblePath('salesChannelId', 'themeId'), $path);
    }

    public function testGenerateNewPathEqualsIgnoresSeed(): void
    {
        $builder = new MD5ThemePathBuilder();

        static::assertEquals(
            $builder->generateNewPath('salesChannelId', 'themeId', 'foo'),
            $builder->generateNewPath('salesChannelId', 'themeId', 'bar')
        );
    }
}
