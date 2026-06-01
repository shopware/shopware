<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Cms\CmsExtensions;

/**
 * @internal
 */
#[CoversClass(CmsExtensions::class)]
class CmsExtensionsTest extends TestCase
{
    public function testCreateFromXmlWithBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/Resources/cms.xml');

        static::assertSame(__DIR__ . '/../_fixtures/Resources', $cmsExtensions->getPath());
        static::assertNotNull($cmsExtensions->getBlocks());
        static::assertCount(2, $cmsExtensions->getBlocks()->getBlocks());
    }

    public function testCreateFromXmlWithoutBlocks(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/Resources/cms-without-blocks.xml');

        static::assertSame(__DIR__ . '/../_fixtures/Resources', $cmsExtensions->getPath());
        static::assertNull($cmsExtensions->getBlocks());
    }

    public function testSetPath(): void
    {
        $cmsExtensions = CmsExtensions::createFromXmlFile(__DIR__ . '/../_fixtures/Resources/cms.xml');

        $cmsExtensions->setPath('test');
        static::assertSame('test', $cmsExtensions->getPath());
    }

    public function testThrowsXmlParsingExceptionIfDuplicateCategory(): void
    {
        $file = __DIR__ . '/../_fixtures/Resources/cms-duplicate-category.xml';

        $this->expectExceptionObject(AppException::xmlParsingException(
            $file,
            '[ERROR 1871] Element \'category\': This element is not expected. Expected is ( label ).'
        ));

        CmsExtensions::createFromXmlFile($file);
    }

    public function testThrowsXmlParsingExceptionIfDuplicateSlotName(): void
    {
        $file = __DIR__ . '/../_fixtures/Resources/cms-duplicate-slot-name.xml';

        $this->expectExceptionObject(AppException::xmlParsingException(
            $file,
            '[ERROR 1877] Element \'slot\': Duplicate key-sequence [\'left\'] in unique identity-constraint \'uniqueSlotName\'.'
        ));

        CmsExtensions::createFromXmlFile($file);
    }
}
