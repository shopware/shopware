<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Cms;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Cms\CmsExtensions;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 */
class CmsExtensionsTest extends TestCase
{
    public function testCreateFromXmlWithBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithBlocks.xml');

        static::assertSame(__DIR__ . '/_fixtures/valid', $cmsExtensions->getPath());
        static::assertNotNull($cmsExtensions->getBlocks());
        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());
    }

    public function testCreateFromXmlWithoutBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithoutBlocks.xml');

        static::assertSame(__DIR__ . '/_fixtures/valid', $cmsExtensions->getPath());
        static::assertNull($cmsExtensions->getBlocks());
    }

    public function testSetPath(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/valid/cmsExtensionsWithBlocks.xml');

        $cmsExtensions->setPath('test');
        static::assertSame('test', $cmsExtensions->getPath());
    }

    public function testThrowsXmlParsingExceptionIfDuplicateCategory(): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            $this->expectException(AppException::class);
        } else {
            $this->expectException(XmlParsingException::class);
        }

        $this->expectExceptionMessage('Element \'category\': This element is not expected. Expected is ( label )');

        CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/invalid/cmsExtensionsWithDuplicateCategory.xml');
    }

    public function testThrowsXmlParsingExceptionIfDuplicateSlotName(): void
    {
        if (Feature::isActive('v6.7.0.0')) {
            $this->expectException(AppException::class);
        } else {
            $this->expectException(XmlParsingException::class);
        }

        $this->expectExceptionMessage('Element \'slot\': Duplicate key-sequence [\'left\'] in unique identity-constraint \'uniqueSlotName\'');

        CmsExtensions::createFromXmlFile(__DIR__ . '/_fixtures/invalid/cmsExtensionsWithDuplicateSlotName.xml');
    }
}
