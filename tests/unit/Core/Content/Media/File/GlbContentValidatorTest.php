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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GlbContentValidator::class)]
class GlbContentValidatorTest extends TestCase
{
    private const INVALID_GLB_MESSAGE = 'The file is not a valid GLB (binary glTF 2.0) document.';

    private GlbContentValidator $validator;

    private Filesystem $filesystem;

    private string $fileName;

    protected function setUp(): void
    {
        $this->validator = new GlbContentValidator();
        $this->filesystem = new Filesystem();
        $this->fileName = $this->filesystem->tempnam(sys_get_temp_dir(), 'glb');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->fileName);
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->validator->getDecorated();
    }

    public function testSupportsGlbCaseInsensitive(): void
    {
        static::assertTrue($this->validator->supports($this->createMediaFile('', 'GLB')));
        static::assertFalse($this->validator->supports($this->createMediaFile('', 'svg')));
    }

    public function testIgnoresOtherFileTypes(): void
    {
        $this->validator->validate($this->createMediaFile('not a glb', 'png'));

        static::assertFileExists($this->fileName);
    }

    public function testSelfContainedGlbPassesValidation(): void
    {
        $file = $this->createMediaFile($this->buildGlb([
            'asset' => ['version' => '2.0'],
            'buffers' => [['byteLength' => 4]],
            'images' => [
                ['bufferView' => 0, 'mimeType' => 'image/png'],
                ['uri' => ' DATA:image/png;base64,iVBORw0KGgo='],
            ],
        ], binaryChunk: "\x00\x00\x00\x00"));

        $this->validator->validate($file);

        static::assertSame('glb', $file->getFileExtension());
    }

    public function testUrlsInMetadataPassValidation(): void
    {
        $file = $this->createMediaFile($this->buildGlb([
            'asset' => ['version' => '2.0', 'copyright' => 'https://agency.example'],
            'extras' => ['uri' => 'https://agency.example'],
            'nodes' => [['name' => 'chair', 'extras' => ['links' => [['uri' => 'https://agency.example/chair']]]]],
            'extensions' => ['VENDOR_metadata' => ['uri' => 'https://agency.example']],
        ]));

        $this->validator->validate($file);

        static::assertSame('glb', $file->getFileExtension());
    }

    /**
     * @param array<string, mixed> $document
     */
    #[DataProvider('externalReferenceProvider')]
    public function testGlbWithExternalReferenceIsRejected(array $document, string $expectedPaths): void
    {
        $file = $this->createMediaFile($this->buildGlb($document));

        static::expectExceptionObject(MediaException::invalidFile('GLB files with external references are not allowed: ' . $expectedPaths));

        $this->validator->validate($file);
    }

    public static function externalReferenceProvider(): \Generator
    {
        yield 'absolute image url is a tracking pixel' => [
            ['asset' => ['version' => '2.0'], 'images' => [['uri' => 'https://tracker.example/pixel.png']]],
            'images[0].uri',
        ];

        yield 'protocol relative buffer url is resolved against the page' => [
            ['asset' => ['version' => '2.0'], 'buffers' => [['byteLength' => 1, 'uri' => '//tracker.example/buffer.bin']]],
            'buffers[0].uri',
        ];

        yield 'relative path is fetched relative to the model url' => [
            ['asset' => ['version' => '2.0'], 'images' => [['uri' => 'texture.png']]],
            'images[0].uri',
        ];

        yield 'non string uri cannot be proven to be embedded' => [
            ['asset' => ['version' => '2.0'], 'images' => [['uri' => ['https://tracker.example']]]],
            'images[0].uri',
        ];

        yield 'all offending references are reported' => [
            ['asset' => ['version' => '2.0'], 'images' => [['uri' => 'data:image/png;base64,AA=='], ['uri' => 'https://a.example'], ['uri' => 'https://b.example']]],
            'images[1].uri, images[2].uri',
        ];

        yield 'image uri is rejected even if a bufferView is present' => [
            ['asset' => ['version' => '2.0'], 'images' => [['bufferView' => 0, 'mimeType' => 'image/png', 'uri' => 'https://tracker.example/pixel.png']]],
            'images[0].uri',
        ];
    }

    #[DataProvider('invalidContainerProvider')]
    public function testInvalidGlbContainerIsRejected(string $content): void
    {
        $file = $this->createMediaFile($content);

        static::expectExceptionObject(MediaException::invalidFile(self::INVALID_GLB_MESSAGE));

        $this->validator->validate($file);
    }

    public static function invalidContainerProvider(): \Generator
    {
        $json = '{"asset":{"version":"2.0"}}';

        yield 'plain glTF JSON would be parsed by loaders without the binary magic' => [
            '{"asset":{"version":"2.0"},"images":[{"uri":"https://tracker.example/pixel.png"}]}',
        ];

        yield 'file shorter than the headers' => ['glTF'];

        yield 'glTF 1.0 binary container is not supported' => [
            pack('a4VV', 'glTF', 1, 20 + \strlen($json)) . pack('VV', \strlen($json), 0x4E4F534A) . $json,
        ];

        yield 'first chunk is not the JSON chunk' => [
            pack('a4VV', 'glTF', 2, 20 + \strlen($json)) . pack('VV', \strlen($json), 0x004E4942) . $json,
        ];

        yield 'declared length exceeds the file size' => [
            pack('a4VV', 'glTF', 2, 1000) . pack('VV', \strlen($json), 0x4E4F534A) . $json,
        ];

        yield 'JSON chunk exceeds the declared length' => [
            pack('a4VV', 'glTF', 2, 20 + \strlen($json)) . pack('VV', \strlen($json) + 4, 0x4E4F534A) . $json . '    ',
        ];

        yield 'JSON chunk is not valid JSON' => [
            pack('a4VV', 'glTF', 2, 24) . pack('VV', 4, 0x4E4F534A) . '{"a ',
        ];

        yield 'images is not a list of objects' => [
            pack('a4VV', 'glTF', 2, 32) . pack('VV', 12, 0x4E4F534A) . '{"images":1}',
        ];

        yield 'image is not an object' => [
            pack('a4VV', 'glTF', 2, 36) . pack('VV', 16, 0x4E4F534A) . '{"images":[1]}  ',
        ];

        yield 'JSON chunk is not an object' => [
            pack('a4VV', 'glTF', 2, 24) . pack('VV', 4, 0x4E4F534A) . '"x" ',
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function buildGlb(array $document, string $binaryChunk = ''): string
    {
        $json = json_encode($document, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $json = str_pad($json, (int) ceil(\strlen($json) / 4) * 4, ' ');

        $chunks = pack('VV', \strlen($json), 0x4E4F534A) . $json;
        if ($binaryChunk !== '') {
            $chunks .= pack('VV', \strlen($binaryChunk), 0x004E4942) . $binaryChunk;
        }

        return pack('a4VV', 'glTF', 2, 12 + \strlen($chunks)) . $chunks;
    }

    private function createMediaFile(string $content, string $extension = 'glb'): MediaFile
    {
        $this->filesystem->dumpFile($this->fileName, $content);

        return new MediaFile($this->fileName, 'model/gltf-binary', $extension, \strlen($content));
    }
}
