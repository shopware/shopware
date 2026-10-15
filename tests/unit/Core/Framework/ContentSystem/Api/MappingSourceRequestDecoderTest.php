<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\MappingSourceRequestDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingSourceRequestDecoder::class)]
class MappingSourceRequestDecoderTest extends TestCase
{
    public function testAcceptsATypedSourceReference(): void
    {
        $source = ['type' => 'root', 'id' => 'product', 'path' => 'name'];

        static::assertSame($source, MappingSourceRequestDecoder::decode($source)->jsonSerialize());
    }

    /**
     * @param array<string, mixed> $source
     */
    #[DataProvider('malformedSources')]
    public function testMalformedSourceIsAClientError(array $source): void
    {
        try {
            MappingSourceRequestDecoder::decode($source);
            static::fail('Expected the malformed source to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAPPING_SOURCE, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedSources(): iterable
    {
        yield 'missing type' => [['id' => 'product']];
        yield 'missing id' => [['type' => 'root']];
        yield 'empty path' => [['type' => 'root', 'id' => 'product', 'path' => '']];
        yield 'invalid config' => [['type' => 'root', 'id' => 'product', 'config' => 'no']];
        yield 'unknown key' => [['type' => 'root', 'id' => 'product', 'extra' => true]];
    }
}
