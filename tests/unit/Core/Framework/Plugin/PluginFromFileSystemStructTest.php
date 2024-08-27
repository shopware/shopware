<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;

/**
 * @internal
 */
#[CoversClass(PluginFromFileSystemStruct::class)]
class PluginFromFileSystemStructTest extends TestCase
{
    #[DataProvider('dataProviderTestGetName')]
    public function testGetName(PluginFromFileSystemStruct $pluginFromFileSystem, string $expectedResult): void
    {
        static::assertSame($expectedResult, $pluginFromFileSystem->getName());
    }

    /**
     * @return list<array{PluginFromFileSystemStruct, string}>
     */
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
