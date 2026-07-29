<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\Util\Base64;

/**
 * @internal
 */
#[CoversClass(CompressedCriteriaDecoder::class)]
class CompressedCriteriaDecoderTest extends TestCase
{
    private CompressedCriteriaDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new CompressedCriteriaDecoder();
    }

    /**
     * @param array<string, mixed> $criteriaData
     */
    #[DataProvider('validCriteriaProvider')]
    public function testDecodeValidCriteria(array $criteriaData): void
    {
        $encoded = self::encodeCompressedCriteria($criteriaData);
        $decoded = $this->decoder->decode($encoded);

        static::assertSame($criteriaData, $decoded);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function validCriteriaProvider(): iterable
    {
        yield 'empty array' => [
            [],
        ];

        yield 'complex criteria' => [
            [
                'limit' => 10,
                'page' => 2,
                'includes' => [
                    'product' => ['id', 'name', 'description', 'price'],
                    'media' => ['url', 'width', 'height'],
                ],
                'filter' => [
                    ['type' => 'equals', 'field' => 'active', 'value' => true],
                    ['type' => 'range', 'field' => 'price', 'parameters' => ['gte' => 10, 'lte' => 100]],
                ],
                'sort' => [
                    ['field' => 'name', 'order' => 'ASC'],
                    ['field' => 'price', 'order' => 'DESC'],
                ],
                'term' => 'search term',
                'total-count-mode' => 'exact',
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCriteriaParameterProvider(): iterable
    {
        yield 'too long criteria string' => [
            str_repeat('a', 1024 * 128 + 1),
            'The _criteria parameter is too long',
        ];

        yield 'invalid gzip data' => [
            'invalid_base64_data',
            'Unable to decompress gzipped data',
        ];

        yield 'invalid base64 format' => [
            'invalid-base64-format!@#$%',
            'Failed to decode base64url data',
        ];

        yield 'invalid JSON data' => [
            self::gzipAndBase64UrlEncode('{"limit": 25, "invalid": }'),
            'Invalid JSON data',
        ];

        yield 'invalid criteria not array' => [
            self::gzipAndBase64UrlEncode('"just a string"'),
            'Criteria data must be an array',
        ];
    }

    #[DataProvider('invalidCriteriaParameterProvider')]
    #[WithoutErrorHandler]
    public function testInvalidCriteriaParameterThrowsException(string $encodedCriteria, string $expectedMessage): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::invalidCompressedCriteriaParameter($expectedMessage));
        $this->decoder->decode($encodedCriteria);
    }

    #[WithoutErrorHandler]
    public function testDecodeThrowsExceptionWhenDecompressedSizeExceedsLimit(): void
    {
        $decoder = new CompressedCriteriaDecoder(
            compressedCriteriaLengthLimit: 200, // such small criteria can be larger when compressed
            decompressedCriteriaLengthLimit: 100
        );

        // Create criteria that will be larger than 100 bytes when decompressed
        $largeCriteria = [
            'filter' => array_fill(0, 100, ['type' => 'equals', 'field' => 'test', 'value' => 'value']),
        ];

        $encoded = self::encodeCompressedCriteria($largeCriteria);

        $this->expectExceptionObject(DataAbstractionLayerException::invalidCompressedCriteriaParameter('Unable to decompress gzipped data'));

        $decoder->decode($encoded);
    }

    public function testCustomCompressedLengthLimit(): void
    {
        $decoder = new CompressedCriteriaDecoder(compressedCriteriaLengthLimit: 100);

        $tooLongString = str_repeat('a', 101);

        $this->expectExceptionObject(DataAbstractionLayerException::invalidCompressedCriteriaParameter('The _criteria parameter is too long'));

        $decoder->decode($tooLongString);
    }

    /**
     * Helper method to gzip and base64url encode data for testing invalid inputs.
     */
    private static function gzipAndBase64UrlEncode(string $data): string
    {
        $gzippedData = gzencode($data);
        static::assertNotFalse($gzippedData, 'Failed to gzip data');

        return Base64::urlEncode($gzippedData);
    }

    /**
     * Helper method to encode criteria data. We keep it here as a part of specification.
     *
     * @param array<string, mixed>|string $data
     */
    private static function encodeCompressedCriteria(array|string $data): string
    {
        $jsonData = json_encode($data);
        static::assertNotFalse($jsonData, 'Failed to JSON encode data');

        return self::gzipAndBase64UrlEncode($jsonData);
    }
}
