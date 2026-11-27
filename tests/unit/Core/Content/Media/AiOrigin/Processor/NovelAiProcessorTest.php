<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\NovelAiProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NovelAiProcessor::class)]
class NovelAiProcessorTest extends TestCase
{
    #[DataProvider('provenances')]
    public function testProcess(ImageProvenance $provenance, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, (new NovelAiProcessor())->process($provenance));
    }

    public static function provenances(): \Generator
    {
        yield 'Software chunk naming NovelAI is generated' => [new ImageProvenance(textChunks: ['Software' => 'NovelAI']), MediaAiOrigin::Generated];
        yield 'Comment with generation parameters is generated' => [
            new ImageProvenance(textChunks: ['Comment' => '{"prompt":"a cat","steps":28,"sampler":"k_euler","seed":1}']),
            MediaAiOrigin::Generated,
        ];
        yield 'Comment with steps but no sampler is ignored' => [new ImageProvenance(textChunks: ['Comment' => '{"steps":3}']), null];
        yield 'plain text comment is ignored' => [new ImageProvenance(textChunks: ['Comment' => 'Holiday 2026']), null];
        yield 'invalid JSON comment is ignored' => [new ImageProvenance(textChunks: ['Comment' => '{"steps":']), null];
    }
}
