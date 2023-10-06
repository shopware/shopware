<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;

/**
 * @internal
 */
class PluginFromFileSystemStructTest extends TestCase
{
    /**
     * @dataProvider dataProviderTestGetName
     */
    public function testGetName(PluginFromFileSystemStruct $pluginFromFileSystem, string $expectedResult): void
    {
        static::assertSame($expectedResult, $pluginFromFileSystem->getName());
    }

    public static function dataProviderTestGetName(): array
    {
        return [
            [
                self::getPluginFromFileSystemStructWithBaseClass('SwagFoo\\SwagFoo'),
                'SwagFoo',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('Swag\\PayPal\\SwagPayPal\\SwagPayPalExtension'),
                'SwagPayPalExtension',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('//Swag\\PayPal\\SwagPay/Pal\\SwagPayPal-Extension'),
                'SwagPayPal-Extension',
            ],
            [
                self::getPluginFromFileSystemStructWithBaseClass('Test'),
                'Test',
            ],
        ];
    }

    private static function getPluginFromFileSystemStructWithBaseClass(string $baseClass): PluginFromFileSystemStruct
    {
        return (new PluginFromFileSystemStruct())->assign([
            'baseClass' => $baseClass,
        ]);
    }
}
