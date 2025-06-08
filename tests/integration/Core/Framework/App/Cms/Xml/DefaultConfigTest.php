<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

/**
 * @internal
 */
class DefaultConfigTest extends TestCase
{
    public function testDefaultConfigFromXml(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');
        static::assertNotNull($cmsExtensions->getBlocks());

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

        static::assertSame('5px', $defaultConfig->getMarginBottom());
        static::assertSame('10px', $defaultConfig->getMarginTop());
        static::assertSame('15px', $defaultConfig->getMarginLeft());
        static::assertSame('20px', $defaultConfig->getMarginRight());
        static::assertSame('boxed', $defaultConfig->getSizingMode());
        static::assertSame('#000', $defaultConfig->getBackgroundColor());
    }
}
