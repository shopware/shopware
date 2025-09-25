<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;

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

    public function testDecodeValidCriteria(): void
    {
        $criteriaData = [
            'limit' => 25,
            'page' => 2,
            'filter' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
            'sort' => [
                ['field' => 'id', 'order' => 'ASC'],
            ],
            'includes' => ['product', 'category'],
        ];

        // Compress and encode the criteria data
        $jsonData = json_encode($criteriaData, \JSON_THROW_ON_ERROR);
        $encodedCriteria = self::gzipAndBase64UrlEncode($jsonData);

        $result = $this->decoder->decode($encodedCriteria);

        static::assertSame($criteriaData, $result);
    }

    public function testDecodeCriteriaPreparedInJs(): void
    {
        // This is a real-world example of encoded criteria
        $encodedCriteria = 'H4sIAHfzwmgAA31UTW_bMAz9LzrnsO2Y25ChWLEVKNbuNBQGI7M2UVny9JHMK_rfR0qu7CRdT5Yp8j3q8UnPytBAUW0_ftioETpU208bRVab1GJQ22elh9DIRhOM47xfilq1UXEakT85tlF74_TTtcS1s4_U8aKFCLID-qnzLtn2BluCG9dK2VlUPXDhK03GEh7BDgtVpl0Kd844_wYU94Q6krO3LpB8T8DnvXqMy-pMv6IN9Jdsl_teAVUAC0POKrirulkIrhm9a5PO0g0zyRz6gQfCoxTNMCy59jTmFjfKQ2Tqzwf0wsiQYHQyELHd9QgjhnjrSZ_u_CciHEvoOwVBrrmOKaQr8GhjHmOZsQcbcgH_uNzVdStI3P6IPlKGHcCmR9Ax-QwS0P30JvN5F8IdGsNU8g8HIAN7g3dRJswJKUQ3XBGajMo_OTxghHuKRlqT9ZcTUSTyDaej86WV5HUPgTFxDCu1myJ11Zx3aiT2adhbbkYAjtTGnr89UteLl5M3NbupqVInO28UsFbijKpuM2ZdpcBSHdHvBDZSnHhpWP4SXvUrLhybonJ1Vw6eTyJ75bxyuZjvpQozTyPfLscD5KWMwcvcr8hE9CIJGhw4kOUtzitvBI-oFJ1Oc4lFF0Ekgq7z2LF75UYUevHL9F6r5eTru0BhNDDdy3W6wFgJdXng2b5aXoiv-GdX3pzXu1d6WDtlbeBzQDaveHLBfXh5-Qd2KWaANAUAAA';

        $result = $this->decoder->decode($encodedCriteria);

        // Verify basic criteria properties
        static::assertSame(10, $result['limit']);
        static::assertSame(2, $result['page']);

        // Verify includes are properly set
        static::assertArrayHasKey('includes', $result);
        $includes = $result['includes'];
        static::assertIsArray($includes);
        static::assertArrayHasKey('product', $includes);
        static::assertArrayHasKey('media', $includes);
        static::assertArrayHasKey('product_media', $includes);
        static::assertArrayHasKey('calculated_price', $includes);

        // Verify specific includes contain expected fields
        static::assertContains('name', $includes['product']);
        static::assertContains('description', $includes['product']);
        static::assertContains('ratingAverage', $includes['product']);
        static::assertContains('url', $includes['media']);
        static::assertContains('width', $includes['media']);
        static::assertContains('height', $includes['media']);
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

        yield 'invalid base64 data' => [
            'invalid_base64_data',
            'Unable to decompress gzipped data',
        ];

        yield 'invalid base64 format' => [
            'invalid-base64-format-with-special-chars!@#$%',
            'Unable to decode base64 data',
        ];

        // Create invalid JSON data, compress and encode it
        $encodedInvalidJson = self::gzipAndBase64UrlEncode('{"limit": 25, "invalid": }');
        yield 'invalid JSON data' => [
            $encodedInvalidJson,
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
        $this->expectExceptionObject(DataAbstractionLayerException::invalidCriteriaParameter($expectedMessage));

        $this->decoder->decode($encodedCriteria);
    }

    public function testDecodeEmptyArray(): void
    {
        $criteriaData = [];
        $jsonData = json_encode($criteriaData, \JSON_THROW_ON_ERROR);
        $encodedCriteria = self::gzipAndBase64UrlEncode($jsonData);

        $result = $this->decoder->decode($encodedCriteria);

        static::assertSame([], $result);
    }

    /**
     * base64 URL encoding without padding as described in RFC 4648.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function gzipAndBase64UrlEncode(string $data): string
    {
        $gzippedData = gzencode($data);
        static::assertNotFalse($gzippedData, 'Gzip compressing failed');

        return self::base64UrlEncode($gzippedData);
    }
}
