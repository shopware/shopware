<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\StableDiffusionWebUiProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StableDiffusionWebUiProcessor::class)]
class StableDiffusionWebUiProcessorTest extends TestCase
{
    #[DataProvider('provenances')]
    public function testProcess(ImageProvenance $provenance, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, (new StableDiffusionWebUiProcessor())->process($provenance));
    }

    public static function provenances(): \Generator
    {
        $parameters = "a red sneaker on white background\nNegative prompt: blurry\nSteps: 30, Sampler: DPM++ 2M Karras, CFG scale: 7, Seed: 1234, Size: 1024x1024, Model: sdxl";

        yield 'PNG parameters chunk is generated' => [new ImageProvenance(textChunks: ['parameters' => $parameters]), MediaAiOrigin::Generated];
        yield 'JPEG/WebP user comment is generated' => [new ImageProvenance(exif: ['UserComment' => $parameters]), MediaAiOrigin::Generated];
        yield 'other parameters text is ignored' => [new ImageProvenance(textChunks: ['parameters' => 'exposure: 1/200']), null];
        yield 'parameters in another chunk are ignored' => [new ImageProvenance(textChunks: ['Description' => $parameters]), null];
    }
}
