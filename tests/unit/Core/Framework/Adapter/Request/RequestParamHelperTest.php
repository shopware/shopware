<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestParamHelper::class)]
class RequestParamHelperTest extends TestCase
{
    public function testHelper(): void
    {
        $request = new Request(
            query: ['scalar' => 'query', 'non-scalar' => ['query']],
            request: ['scalar' => 'request', 'non-scalar' => ['request']],
            attributes: ['scalar' => 'attributes', 'non-scalar' => ['attributes']],
        );

        // test fallback with scalar value
        static::assertSame('test', RequestParamHelper::get($request, 'not-existing', 'test'));

        // test fallback with non-scalar value
        static::assertSame(['test'], RequestParamHelper::get($request, 'not-existing', ['test']));

        // test fallback without default
        static::assertNull(RequestParamHelper::get($request, 'not-existing'));

        // test query takes precedence over request, attributes are ignored
        static::assertSame('query', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['query'], RequestParamHelper::get($request, 'non-scalar'));

        $request->query->remove('scalar');
        $request->query->remove('non-scalar');

        // test request value is used if query is empty
        static::assertSame('request', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['request'], RequestParamHelper::get($request, 'non-scalar'));
    }

    /**
     * @deprecated tag:v6.8.0 - Can be removed when fallback to attributes is removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testHelperDeprecated(): void
    {
        $request = new Request(
            query: ['scalar' => 'query', 'non-scalar' => ['query']],
            request: ['scalar' => 'request', 'non-scalar' => ['request']],
            attributes: ['scalar' => 'attributes', 'non-scalar' => ['attributes']],
        );

        // test fallback with scalar value
        static::assertSame('test', RequestParamHelper::get($request, 'not-existing', 'test'));

        // test fallback with non-scalar value
        static::assertSame(['test'], RequestParamHelper::get($request, 'not-existing', ['test']));

        // test fallback without default
        static::assertNull(RequestParamHelper::get($request, 'not-existing'));

        // test attributes takes precedence over query
        static::assertSame('attributes', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['attributes'], RequestParamHelper::get($request, 'non-scalar'));

        $request->attributes->remove('scalar');
        $request->attributes->remove('non-scalar');

        // test query takes precedence over request
        static::assertSame('query', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['query'], RequestParamHelper::get($request, 'non-scalar'));

        $request->query->remove('scalar');
        $request->query->remove('non-scalar');

        // test request value is used if query is empty
        static::assertSame('request', RequestParamHelper::get($request, 'scalar'));
        static::assertSame(['request'], RequestParamHelper::get($request, 'non-scalar'));
    }
}
