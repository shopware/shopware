<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\FileInfoHelper
 */
#[Package('buyers-experience')]
class FileInfoHelperTest extends TestCase
{
    public function testGetMimeTypeWithDetectableTypeByFileContentWillDetectByContent(): void
    {
        static::assertEquals('image/png', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/image1x1.png', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentWillDetectByExtension(): void
    {
        static::assertEquals('model/gltf-binary', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentAndByExtensionWillReturnCommonType(): void
    {
        static::assertEquals('application/octet-stream', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary'));
    }
}
