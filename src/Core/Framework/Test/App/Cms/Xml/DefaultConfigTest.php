<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

class DefaultConfigTest extends TestCase
{
    public function testDefaultConfigFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        $defaultConfig = $cmsExtensions->getBlocks()->getBlocks()[0]->getDefaultConfig();

        static::assertEquals(
            [
                'marginBottom' => '5px',
                'marginTop' => '10px',
                'marginLeft' => '15px',
                'marginRight' => '20px',
                'sizingMode' => 'boxed',
                'backgroundColor' => '#000',
            ],
            $defaultConfig->toArray('en-GB')
        );

        static::assertEquals('5px', $defaultConfig->getMarginBottom());
        static::assertEquals('10px', $defaultConfig->getMarginTop());
        static::assertEquals('15px', $defaultConfig->getMarginLeft());
        static::assertEquals('20px', $defaultConfig->getMarginRight());
        static::assertEquals('boxed', $defaultConfig->getSizingMode());
        static::assertEquals('#000', $defaultConfig->getBackgroundColor());
    }
}
