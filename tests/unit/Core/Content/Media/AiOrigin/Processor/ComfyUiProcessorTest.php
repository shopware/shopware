<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\ComfyUiProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ComfyUiProcessor::class)]
class ComfyUiProcessorTest extends TestCase
{
    #[DataProvider('provenances')]
    public function testProcess(ImageProvenance $provenance, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, (new ComfyUiProcessor())->process($provenance));
    }

    public static function provenances(): \Generator
    {
        $prompt = '{"3":{"class_type":"KSampler","inputs":{"seed":1}},"9":{"class_type":"SaveImage","inputs":{}}}';
        $workflow = '{"last_node_id":9,"nodes":[{"id":3,"type":"KSampler"}],"links":[]}';

        yield 'PNG prompt chunk in API format is generated' => [new ImageProvenance(textChunks: ['prompt' => $prompt]), MediaAiOrigin::Generated];
        yield 'PNG workflow chunk in UI format is generated' => [new ImageProvenance(textChunks: ['workflow' => $workflow]), MediaAiOrigin::Generated];
        yield 'WebP EXIF with prefixed workflow is generated' => [new ImageProvenance(exif: ['Make' => 'workflow:' . $workflow]), MediaAiOrigin::Generated];
        yield 'WebP EXIF with prefixed prompt is generated' => [new ImageProvenance(exif: ['Model' => 'prompt:' . $prompt]), MediaAiOrigin::Generated];
        yield 'plain text prompt from another tool is ignored' => [new ImageProvenance(textChunks: ['prompt' => 'a cat']), null];
        yield 'JSON without nodes is ignored' => [new ImageProvenance(textChunks: ['prompt' => '{"text":"a cat"}']), null];
        yield 'invalid JSON is ignored' => [new ImageProvenance(textChunks: ['workflow' => '{"nodes":']), null];
        yield 'camera make is ignored' => [new ImageProvenance(exif: ['Make' => 'Canon']), null];
    }
}
