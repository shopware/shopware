<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaAiOrigin::class)]
class MediaAiOriginTest extends TestCase
{
    #[DataProvider('digitalSourceTypes')]
    public function testFromDigitalSourceType(string $digitalSourceType, ?MediaAiOrigin $expected): void
    {
        static::assertSame($expected, MediaAiOrigin::fromDigitalSourceType($digitalSourceType));
    }

    public static function digitalSourceTypes(): \Generator
    {
        yield 'trained algorithmic media is generated' => ['http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia', MediaAiOrigin::Generated];
        yield 'composite with trained algorithmic media is modified' => ['http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia', MediaAiOrigin::Modified];
        yield 'https variant of the vocabulary is accepted' => ['https://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia', MediaAiOrigin::Generated];
        yield 'bare term code without namespace is accepted' => ['compositeWithTrainedAlgorithmicMedia', MediaAiOrigin::Modified];
        yield 'surrounding whitespace is ignored' => ["  http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia\n", MediaAiOrigin::Generated];
        yield 'composite synthetic (composite including generative AI elements) is modified' => ['http://cv.iptc.org/newscodes/digitalsourcetype/compositeSynthetic', MediaAiOrigin::Modified];
        yield 'capitalised term as documented by Google Merchant Center is accepted' => ['TrainedAlgorithmicMedia', MediaAiOrigin::Generated];
        yield 'algorithmic media without training data is not AI' => ['http://cv.iptc.org/newscodes/digitalsourcetype/algorithmicMedia', null];
        yield 'digital capture is not AI' => ['http://cv.iptc.org/newscodes/digitalsourcetype/digitalCapture', null];
        yield 'term from another vocabulary is not AI' => ['http://example.com/trainedAlgorithmicMedia', null];
        yield 'empty value is not AI' => ['', null];
    }
}
