<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Cms;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

class CmsExtensionsTest extends TestCase
{
    public function testCreateFromXmlWithBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertEquals(__DIR__ . '/_fixtures/valid', $cmsExtensions->getPath());
        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());
    }

    public function testCreateFromXmlWithoutBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithoutBlocks.xml');

        static::assertEquals(__DIR__ . '/_fixtures/valid', $cmsExtensions->getPath());
        static::assertNull($cmsExtensions->getBlocks());
    }

    public function testSetPath(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithBlocks.xml');

        $cmsExtensions->setPath('test');
        static::assertEquals('test', $cmsExtensions->getPath());
    }

    public function testThrowsXmlParsingExceptionIfDuplicateCategory(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage("Element 'category': This element is not expected. Expected is ( label )");

        CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/invalid/cmsExtensionsWithDuplicateCategory.xml');
    }

    public function testThrowsXmlParsingExceptionIfDuplicateSlotName(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessage("Element 'slot': Duplicate key-sequence ['left'] in unique identity-constraint 'uniqueSlotName'");

        CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/invalid/cmsExtensionsWithDuplicateSlotName.xml');
    }
}
