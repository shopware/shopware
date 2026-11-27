<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\GeneratorSoftwareProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GeneratorSoftwareProcessor::class)]
class GeneratorSoftwareProcessorTest extends TestCase
{
    #[DataProvider('provenances')]
    public function testProcess(ImageProvenance $provenance, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, (new GeneratorSoftwareProcessor())->process($provenance));
    }

    public static function provenances(): \Generator
    {
        yield 'EXIF software naming DALL-E is generated' => [new ImageProvenance(exif: ['Software' => 'DALL-E 3 via ChatGPT']), MediaAiOrigin::Generated];
        yield 'PNG software chunk naming Stable Diffusion is generated' => [new ImageProvenance(textChunks: ['Software' => 'Stable Diffusion XL']), MediaAiOrigin::Generated];
        yield 'XMP creator tool naming Image Playground is generated' => [
            new ImageProvenance(xmpPackets: [Image::xmpPacket('<xmp:CreatorTool>Image Playground</xmp:CreatorTool>')]),
            MediaAiOrigin::Generated,
        ];
        yield 'XMP creator tool naming an editor says nothing about AI' => [
            new ImageProvenance(xmpPackets: [Image::xmpPacket('<xmp:CreatorTool>Adobe Photoshop 27.0</xmp:CreatorTool>')]),
            null,
        ];
        yield 'image editor says nothing about AI' => [new ImageProvenance(exif: ['Software' => 'Adobe Photoshop 27.0']), null];
        yield 'camera firmware says nothing about AI' => [new ImageProvenance(exif: ['Software' => 'G998BXXU5CVDB']), null];
    }
}
