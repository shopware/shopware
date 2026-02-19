<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RequestDataExtractor::class)]
class RequestDataExtractorTest extends TestCase
{
    private RequestDataExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new RequestDataExtractor();
    }

    /**
     * @return iterable<string, array{array<string, ParameterBinding>|null}>
     */
    public static function passThroughBindingsProvider(): iterable
    {
        yield 'null bindings' => [null];
        yield 'empty bindings array' => [[]];
    }

    /**
     * @param array<string, ParameterBinding>|null $bindings
     */
    #[DataProvider('passThroughBindingsProvider')]
    #[TestDox('returns all scalar query params when no effective bindings')]
    public function testReturnsAllScalarParamsWhenNoEffectiveBindings(?array $bindings): void
    {
        $request = new Request(['page' => '1', 'sort' => 'name']);

        $result = $this->extractor->extractData($request, $bindings);

        static::assertSame(['page' => '1', 'sort' => 'name'], $result);
    }

    #[TestDox('maps params to placeholder names when bindings are provided')]
    public function testMapsParamsToPlaceholderNamesWhenBindingsProvided(): void
    {
        $request = new Request(['seoUrl' => 'my-product']);
        $bindings = [
            'seoUrl' => new ParameterBinding('seoUrl', 'productSlug'),
        ];

        $result = $this->extractor->extractData($request, $bindings);

        static::assertSame(['productSlug' => 'my-product'], $result);
    }

    #[TestDox('uses param name as fallback when placeholder is null')]
    public function testUsesParamNameAsFallbackWhenPlaceholderIsNull(): void
    {
        $request = new Request(['page' => '3']);
        $bindings = [
            'page' => new ParameterBinding('page', null),
        ];

        $result = $this->extractor->extractData($request, $bindings);

        static::assertSame(['page' => '3'], $result);
    }

    #[TestDox('filters out non-scalar query params')]
    public function testFiltersOutNonScalarQueryParams(): void
    {
        $request = new Request(['page' => '1', 'filters' => ['color' => 'red']]);

        $result = $this->extractor->extractData($request, null);

        static::assertSame(['page' => '1'], $result);
    }
}
