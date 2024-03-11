<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(FileInfoHelper::class)]
class FileInfoHelperTest extends TestCase
{
    public function testGetMimeTypeWithDetectableTypeByFileContentWillDetectByContent(): void
    {
        static::assertSame('image/png', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/image1x1.png', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentWillDetectByExtension(): void
    {
        static::assertSame('model/gltf-binary', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary', 'glb'));
    }

    public function testGetMimeTypeWithNotDetectableTypeByFileContentAndByExtensionWillReturnCommonType(): void
    {
        static::assertSame('application/octet-stream', FileInfoHelper::getMimeType(__DIR__ . '/_fixtures/binary'));
    }
}
