<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaParameterResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CriteriaParameterResolver::class)]
class CriteriaParameterResolverTest extends TestCase
{
    private CompressedCriteriaDecoder&MockObject $criteriaDecoder;

    private CriteriaParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->criteriaDecoder = $this->createMock(CompressedCriteriaDecoder::class);
        $this->resolver = new CriteriaParameterResolver($this->criteriaDecoder);
    }

    /**
     * @param array<string, mixed>|null $decodedCriteria
     */
    #[DataProvider('getParameterProvider')]
    public function testGetParameter(mixed $expected, string $key, mixed $fallback, Request $request, ?array $decodedCriteria = null): void
    {
        if ($decodedCriteria !== null) {
            $this->criteriaDecoder->expects($this->once())
                ->method('decode')
                ->with($request->query->get('_criteria'))
                ->willReturn($decodedCriteria);
        } else {
            $this->criteriaDecoder->expects($this->never())
                ->method('decode');
        }

        $got = $this->resolver->getParameter($request, $key, $fallback);
        static::assertSame($expected, $got);
    }

    /**
     * @return iterable<string, array{expected: mixed, key: string, fallback: mixed, request: Request, decodedCriteria?: array<string, mixed>|null}>
     */
    public static function getParameterProvider(): iterable
    {
        yield 'empty' => [
            'expected' => 'fallback',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(),
        ];

        yield 'value from post body' => [
            'expected' => 'post_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request([], ['test_key' => 'post_value'], [], [], [], ['REQUEST_METHOD' => 'POST']),
        ];

        yield 'value from query has priority over post' => [
            'expected' => 'get_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['test_key' => 'get_value'], ['test_key' => 'post_value'], [], [], [], ['REQUEST_METHOD' => 'POST']),
        ];

        yield 'value from attributes has priority over query' => [
            'expected' => 'attribute_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['test_key' => 'get_value'], ['test_key' => 'post_value'], ['test_key' => 'attribute_value'], [], [], ['REQUEST_METHOD' => 'POST']),
        ];

        yield 'compressed criteria has highest priority' => [
            'expected' => 'decoded_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['test_key' => 'get_value', '_criteria' => 'compressed_value'], ['test_key' => 'post_value'], ['test_key' => 'attribute_value'], [], [], ['REQUEST_METHOD' => 'GET']),
            'decodedCriteria' => ['test_key' => 'decoded_value'],
        ];

        yield 'when compressed criteria is set all other bags are ignored' => [
            'expected' => 'decoded_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['test_key' => 'get_value', '_criteria' => 'compressed_value'], ['test_key' => 'post_value'], ['test_key' => 'attribute_value'], [], [], ['REQUEST_METHOD' => 'GET']),
            'decodedCriteria' => ['test_key' => 'decoded_value'],
        ];

        yield 'compressed criteria is ignored on non-GET requests' => [
            'expected' => 'fallback',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['_criteria' => 'compressed'], [], [], [], [], ['REQUEST_METHOD' => 'POST']),
        ];
    }

    public function testGetParameterCached(): void
    {
        $request = new Request(['_criteria' => 'compressed_value'], [], [], [], [], ['REQUEST_METHOD' => 'GET']);

        $this->criteriaDecoder->expects($this->once())
            ->method('decode')
            ->with($request->query->get('_criteria'))
            ->willReturn(['test_key' => 'decoded_value']);

        static::assertFalse($request->attributes->has(CriteriaParameterResolver::ATTRIBUTE_RESOLVED_CRITERIA));

        // First call, should decode and cache the result in request attributes
        $got = $this->resolver->getParameter($request, 'test_key', 'fallback');
        static::assertSame('decoded_value', $got);
        static::assertTrue($request->attributes->has(CriteriaParameterResolver::ATTRIBUTE_RESOLVED_CRITERIA));

        // Call again to test caching
        $got = $this->resolver->getParameter($request, 'test_key', 'fallback');
        static::assertSame('decoded_value', $got);
    }

    /**
     * @param array<string, mixed>|null $decodedCriteria
     */
    #[DataProvider('getCompressedProvider')]
    public function testGetCompressed(mixed $expected, string $key, mixed $fallback, Request $request, ?array $decodedCriteria = null): void
    {
        if ($decodedCriteria !== null) {
            $this->criteriaDecoder->expects($this->once())
                ->method('decode')
                ->with($request->query->get('_criteria'))
                ->willReturn($decodedCriteria);
        } else {
            $this->criteriaDecoder->expects($this->never())
                ->method('decode');
        }

        $got = $this->resolver->getFromCompressed($request, $key, $fallback);
        static::assertSame($expected, $got);
    }

    /**
     * @return iterable<string, array{expected: mixed, key: string, fallback: mixed, request: Request, decodedCriteria?: array<string, mixed>|null}>
     */
    public static function getCompressedProvider(): iterable
    {
        yield 'empty' => [
            'expected' => 'fallback',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(),
        ];

        yield 'compressed criteria is ignored on non-GET requests' => [
            'expected' => 'fallback',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['_criteria' => 'compressed'], [], [], [], [], ['REQUEST_METHOD' => 'POST']),
        ];

        yield 'compressed criteria' => [
            'expected' => 'decoded_value',
            'key' => 'test_key',
            'fallback' => 'fallback',
            'request' => new Request(['_criteria' => 'compressed'], [], [], [], [], ['REQUEST_METHOD' => 'GET']),
            'decodedCriteria' => ['test_key' => 'decoded_value'],
        ];
    }
}
