<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Cms\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

class BlocksTest extends TestCase
{
    public function testFromXmlWithBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());

        $firstBlock = $cmsExtensions->getBlocks()->getBlocks()[0];
        static::assertEquals('first-block-name', $firstBlock->getName());
        static::assertEquals('text-image', $firstBlock->getCategory());
        static::assertEquals(
            [
                'en-GB' => 'First block from app',
                'de-DE' => 'Erster Block einer App',
            ],
            $firstBlock->getLabel()
        );
    }

    public function testFromXmlWithoutBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/valid/cmsExtensionsWithoutBlocks.xml');

        static::assertNull($cmsExtensions->getBlocks());
    }
}
