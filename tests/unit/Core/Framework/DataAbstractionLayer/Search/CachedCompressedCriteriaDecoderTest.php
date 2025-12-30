<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CachedCompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;

/**
 * @internal
 */
#[CoversClass(CachedCompressedCriteriaDecoder::class)]
class CachedCompressedCriteriaDecoderTest extends TestCase
{
    private CompressedCriteriaDecoder&MockObject $decorated;

    private CachedCompressedCriteriaDecoder $decoder;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(CompressedCriteriaDecoder::class);
        $this->decoder = new CachedCompressedCriteriaDecoder($this->decorated);
    }

    public function testDecodeCachesResult(): void
    {
        $encoded = 'some-encoded-string';
        $expected = ['foo' => 'bar'];

        $this->decorated->expects($this->once())
            ->method('decode')
            ->with($encoded)
            ->willReturn($expected);

        // only one request should hit the decorated service
        $result1 = $this->decoder->decode($encoded);
        static::assertSame($expected, $result1);
        $result2 = $this->decoder->decode($encoded);
        static::assertSame($expected, $result2);
    }

    public function testResetClearsCache(): void
    {
        $encoded = 'some-encoded-string';
        $expected = ['foo' => 'bar'];

        // Expect decode to be called twice because of reset in between
        $this->decorated->expects($this->exactly(2))
            ->method('decode')
            ->with($encoded)
            ->willReturn($expected);

        // Only first and last call should hit the decorated service
        $this->decoder->decode($encoded);
        $this->decoder->decode($encoded);
        $this->decoder->reset();
        $result = $this->decoder->decode($encoded);
        static::assertSame($expected, $result);
    }
}
