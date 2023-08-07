<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\CompilerConfiguration;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Theme\CompilerConfiguration
 */
class CompilerConfigurationTest extends TestCase
{
    public function testGetNotSetValue(): void
    {
        $config = new CompilerConfiguration([]);

        static::assertNull($config->getValue('test'));
    }

    public function testGetSetValue(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertEquals('value', $config->getValue('test'));
    }

    public function testGetWholeConfiguration(): void
    {
        $config = new CompilerConfiguration([
            'test' => 'value',
        ]);

        static::assertEquals([
            'test' => 'value',
        ], $config->getConfiguration());
    }
}
