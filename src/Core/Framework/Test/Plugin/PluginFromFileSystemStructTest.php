<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;

class PluginFromFileSystemStructTest extends TestCase
{
    /**
     * @dataProvider dataProviderTestGetName
     */
    public function testGetName(PluginFromFileSystemStruct $pluginFromFileSystem, string $expectedResult): void
    {
        static::assertSame($expectedResult, $pluginFromFileSystem->getName());
    }

    public function dataProviderTestGetName(): array
    {
        return [
            [
                $this->getPluginFromFileSystemStructWithBaseClass('SwagFoo\\SwagFoo'),
                'SwagFoo',
            ],
            [
                $this->getPluginFromFileSystemStructWithBaseClass('Swag\\PayPal\\SwagPayPal\\SwagPayPalExtension'),
                'SwagPayPalExtension',
            ],
            [
                $this->getPluginFromFileSystemStructWithBaseClass('//Swag\\PayPal\\SwagPay/Pal\\SwagPayPal-Extension'),
                'SwagPayPal-Extension',
            ],
            [
                $this->getPluginFromFileSystemStructWithBaseClass('Test'),
                'Test',
            ],
        ];
    }

    private function getPluginFromFileSystemStructWithBaseClass(string $baseClass): PluginFromFileSystemStruct
    {
        return (new PluginFromFileSystemStruct())->assign([
            'baseClass' => $baseClass,
        ]);
    }
}
