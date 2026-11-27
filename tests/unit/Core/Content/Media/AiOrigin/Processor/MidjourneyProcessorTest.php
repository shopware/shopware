<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ImageProvenance;
use Shopware\Core\Content\Media\AiOrigin\MediaAiOrigin;
use Shopware\Core\Content\Media\AiOrigin\Processor\MidjourneyProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Media\AiOrigin\_fixtures\ProvenanceImageBuilder as Image;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MidjourneyProcessor::class)]
class MidjourneyProcessorTest extends TestCase
{
    public function testDescriptionWithJobIdIsGenerated(): void
    {
        $xmp = Image::xmpPacket('<dc:description><rdf:Alt><rdf:li xml:lang="x-default">a red sneaker --ar 1:1 Job ID: 0f4c1c3e-5a7b-4c2d-9e8f-1a2b3c4d5e6f</rdf:li></rdf:Alt></dc:description>');

        static::assertSame(MediaAiOrigin::Generated, (new MidjourneyProcessor())->process(new ImageProvenance(xmpPackets: [$xmp])));
    }

    public function testDescriptionWithoutJobIdIsIgnored(): void
    {
        $xmp = Image::xmpPacket('<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Job ID: 42</rdf:li></rdf:Alt></dc:description>');

        static::assertNull((new MidjourneyProcessor())->process(new ImageProvenance(xmpPackets: [$xmp])));
    }
}
