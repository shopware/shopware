<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\GlbContentValidator;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GlbContentValidator::class)]
class GlbContentValidatorTest extends TestCase
{
    private const INVALID_GLB_MESSAGE = 'The file is not a valid GLB (binary glTF 2.0) document.';

    private GlbContentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new GlbContentValidator();
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->validator->getDecorated();
    }

    public function testSupportsGlbCaseInsensitive(): void
    {
        static::assertTrue($this->validator->supports($this->createMediaFile('self-contained', 'GLB')));
        static::assertFalse($this->validator->supports($this->createMediaFile('self-contained', 'svg')));
    }

    public function testIgnoresOtherFileTypes(): void
    {
        $file = new MediaFile(__DIR__ . '/_fixtures/plain.txt', 'model/gltf-binary', 'png', 10);

        $this->validator->validate($file);

        static::assertSame('png', $file->getFileExtension());
    }

    public function testSelfContainedGlbPassesValidation(): void
    {
        $file = $this->createMediaFile('self-contained');

        $this->validator->validate($file);

        static::assertSame('glb', $file->getFileExtension());
    }

    public function testUrlsInMetadataPassValidation(): void
    {
        $file = $this->createMediaFile('urls-in-metadata');

        $this->validator->validate($file);

        static::assertSame('glb', $file->getFileExtension());
    }

    #[DataProvider('externalReferenceProvider')]
    public function testGlbWithExternalReferenceIsRejected(string $fixture, string $expectedPaths): void
    {
        $file = $this->createMediaFile($fixture);

        static::expectExceptionObject(MediaException::invalidFile('GLB files with external references are not allowed: ' . $expectedPaths));

        $this->validator->validate($file);
    }

    public static function externalReferenceProvider(): \Generator
    {
        yield 'absolute image url is a tracking pixel' => ['absolute-image-url', 'images[0].uri'];

        yield 'protocol relative buffer url is resolved against the page' => ['protocol-relative-buffer-url', 'buffers[0].uri'];

        yield 'relative path is fetched relative to the model url' => ['relative-image-path', 'images[0].uri'];

        yield 'non string uri cannot be proven to be embedded' => ['non-string-image-uri', 'images[0].uri'];

        yield 'all offending references are reported' => ['multiple-external-images', 'images[1].uri, images[2].uri'];

        yield 'image uri is rejected even if a bufferView is present' => ['image-uri-with-buffer-view', 'images[0].uri'];
    }

    #[DataProvider('invalidContainerProvider')]
    public function testInvalidGlbContainerIsRejected(string $fixture): void
    {
        $file = $this->createMediaFile($fixture);

        static::expectExceptionObject(MediaException::invalidFile(self::INVALID_GLB_MESSAGE));

        $this->validator->validate($file);
    }

    public static function invalidContainerProvider(): \Generator
    {
        yield 'plain glTF JSON would be parsed by loaders without the binary magic' => ['plain-gltf-json'];

        yield 'file shorter than the headers' => ['shorter-than-headers'];

        yield 'glTF 1.0 binary container is not supported' => ['gltf-1-container'];

        yield 'first chunk is not the JSON chunk' => ['first-chunk-not-json'];

        yield 'declared length exceeds the file size' => ['declared-length-exceeds-file-size'];

        yield 'JSON chunk exceeds the declared length' => ['json-chunk-exceeds-declared-length'];

        yield 'JSON chunk is not valid JSON' => ['json-chunk-invalid-json'];

        yield 'images is not a list of objects' => ['images-not-a-list'];

        yield 'image is not an object' => ['image-not-an-object'];

        yield 'JSON chunk is not an object' => ['json-chunk-not-an-object'];
    }

    private function createMediaFile(string $fixture, string $extension = 'glb'): MediaFile
    {
        $fileName = __DIR__ . '/_fixtures/glb/' . $fixture . '.glb';
        $size = filesize($fileName);
        static::assertIsInt($size);

        return new MediaFile($fileName, 'model/gltf-binary', $extension, $size);
    }
}
