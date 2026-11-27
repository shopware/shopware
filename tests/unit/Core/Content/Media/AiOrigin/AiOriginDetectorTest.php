<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\AiOriginDetector;
use Shopware\Core\Content\Media\AiOrigin\ExifReader;
use Shopware\Core\Content\Media\AiOrigin\Format\ImageFormatReader;
use Shopware\Core\Content\Media\AiOrigin\Format\IsoBmffReader;
use Shopware\Core\Content\Media\AiOrigin\Format\JpegReader;
use Shopware\Core\Content\Media\AiOrigin\Format\PngReader;
use Shopware\Core\Content\Media\AiOrigin\Format\WebpReader;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\AiOriginProcessorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AiOriginDetector::class)]
class AiOriginDetectorTest extends TestCase
{
    public function testFirstProcessorWithAResultWins(): void
    {
        $detector = $this->detector($this->filesystemWith(Image::jpeg(comment: 'any')), [
            new FixedAiOriginProcessor(null),
            new FixedAiOriginProcessor(MediaAiOrigin::Modified),
            new FixedAiOriginProcessor(MediaAiOrigin::Generated),
        ]);

        static::assertSame(MediaAiOrigin::Modified, $detector->detect('/image.jpg', 'jpg'));
    }

    public function testNoProcessorResultIsNull(): void
    {
        $detector = $this->detector($this->filesystemWith(Image::jpeg(comment: 'any')), [new FixedAiOriginProcessor(null)]);

        static::assertNull($detector->detect('/image.jpg', 'jpg'));
    }

    public function testImageWithoutMetadataSkipsProcessors(): void
    {
        $processor = new FixedAiOriginProcessor(MediaAiOrigin::Generated);

        static::assertNull($this->detector($this->filesystemWith(Image::jpeg()), [$processor])->detect('/image.jpg', 'jpg'));
        static::assertNull($processor->provenance);
    }

    public function testMissingFileIsNull(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willThrowException(new IOException('not found'));
        $processor = new FixedAiOriginProcessor(MediaAiOrigin::Generated);

        static::assertNull($this->detector($filesystem, [$processor])->detect('/missing.jpg', 'jpg'));
        static::assertNull($processor->provenance);
    }

    public function testHandsTheMetadataOfTheFormatReaderToTheProcessors(): void
    {
        $processor = new FixedAiOriginProcessor(null);

        $this->detector($this->filesystemWith(Image::png(['tEXt' => "parameters\0Steps: 20"])), [$processor])->detect('/image.png', 'png');

        static::assertSame(['parameters' => 'Steps: 20'], $processor->provenance?->textChunks);
    }

    public function testChoosesTheFormatReaderByFileExtensionInAnyCase(): void
    {
        $processor = new FixedAiOriginProcessor(null);

        $this->detector($this->filesystemWith(Image::png(['tEXt' => "parameters\0Steps: 20"])), [$processor])->detect('/image', 'PNG');

        static::assertSame(['parameters' => 'Steps: 20'], $processor->provenance?->textChunks);
    }

    public function testSearchesOtherFormatsForXmp(): void
    {
        $processor = new FixedAiOriginProcessor(null);
        $gif = "GIF89a\x01\x00\x01\x00XMP DataXMP" . Image::xmp(Image::TRAINED) . "\x00\x3B";

        $this->detector($this->filesystemWith($gif), [$processor])->detect('/image.gif', 'gif');

        static::assertCount(1, $processor->provenance->xmpPackets ?? []);
        static::assertStringStartsWith('<x:xmpmeta', $processor->provenance->xmpPackets[0] ?? '');
    }

    public function testFilesUnderAWrongExtensionOrCutOffCauseNoErrors(): void
    {
        $store = Image::c2paStore(['urn:uuid:1' => ['actions' => [['action' => 'c2pa.created', 'digitalSourceType' => Image::TRAINED]]]]);
        $images = [
            Image::jpeg(xmpPackets: [Image::xmp(Image::TRAINED)], c2paSegments: Image::app11Segments($store, chunkSize: 40), comment: 'c'),
            Image::png(['zTXt' => "prompt\0\0" . gzcompress('{"1":{"class_type":"K"}}'), 'iTXt' => "XML:com.adobe.xmp\0\x01\0\0\0" . gzcompress(Image::xmp(Image::TRAINED)), 'caBX' => $store]),
            // compressed text whose data is not zlib, so the decompression fails
            Image::png(['zTXt' => "prompt\0\0not zlib", 'iTXt' => "Comment\0\x01\0\0\0not zlib either"]),
            Image::webp(['XMP ' => Image::xmp(Image::TRAINED), 'C2PA' => $store]),
            Image::avif(c2paStore: $store, xmp: Image::xmp(Image::TRAINED)),
        ];

        // the strictest error handler a project could have, even for warnings silenced with @
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            foreach ($images as $image) {
                foreach ([\strlen($image), 100, 50, 13, 0] as $length) {
                    foreach (['jpg', 'png', 'webp', 'avif', 'gif'] as $extension) {
                        $this->detector($this->filesystemWith(substr($image, 0, $length)), [new FixedAiOriginProcessor(null)])->detect('/image', $extension);
                    }
                }
            }
        } finally {
            restore_error_handler();
        }

        $this->addToAssertionCount(1);
    }

    public function testAFormatReaderCanAddAnotherFileExtension(): void
    {
        $processor = new FixedAiOriginProcessor(null);
        $tiffReader = new FixedFormatReader('tiff', new ImageProvenance(exif: ['Software' => 'Midjourney']));

        (new AiOriginDetector($this->filesystemWith('II*'), [$tiffReader], [$processor], new ExifReader()))->detect('/image.tiff', 'tiff');

        static::assertSame(['Software' => 'Midjourney'], $processor->provenance?->exif);
    }

    public function testTheFirstFormatReaderForTheExtensionReadsTheFile(): void
    {
        $processor = new FixedAiOriginProcessor(null);
        $formatReaders = [
            new FixedFormatReader('jpg', new ImageProvenance(textChunks: ['Comment' => 'first'])),
            new FixedFormatReader('jpg', new ImageProvenance(textChunks: ['Comment' => 'second'])),
        ];

        (new AiOriginDetector($this->filesystemWith(''), $formatReaders, [$processor], new ExifReader()))->detect('/image.jpg', 'jpg');

        static::assertSame(['Comment' => 'first'], $processor->provenance?->textChunks);
    }

    /**
     * @param list<AiOriginProcessorInterface> $processors
     */
    private function detector(Filesystem $filesystem, array $processors): AiOriginDetector
    {
        $exifReader = new ExifReader();
        $formatReaders = [new JpegReader($exifReader), new PngReader($exifReader), new WebpReader($exifReader), new IsoBmffReader($exifReader)];

        return new AiOriginDetector($filesystem, $formatReaders, $processors, $exifReader);
    }

    private function filesystemWith(string $bytes): Filesystem
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('readFile')->willReturn($bytes);

        return $filesystem;
    }
}

/**
 * @internal
 */
class FixedAiOriginProcessor implements AiOriginProcessorInterface
{
    public ?ImageProvenance $provenance = null;

    public function __construct(private readonly ?MediaAiOrigin $origin)
    {
    }

    public function process(ImageProvenance $provenance): ?MediaAiOrigin
    {
        $this->provenance = $provenance;

        return $this->origin;
    }
}

/**
 * @internal
 */
class FixedFormatReader implements ImageFormatReader
{
    public function __construct(
        private readonly string $fileExtension,
        private readonly ImageProvenance $provenance,
    ) {
    }

    public function supports(string $fileExtension): bool
    {
        return $fileExtension === $this->fileExtension;
    }

    public function read(string $bytes): ImageProvenance
    {
        return $this->provenance;
    }
}
