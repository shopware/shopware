<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Controller\InternalRequest;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Symfony\Component\HttpFoundation\Request;

class InternalRequestTest extends TestCase
{
    public function testRequireThrowsException()
    {
        $request = new InternalRequest();
        $this->expectException(MissingParameterException::class);
        $request->require('test');
    }

    public function testConsidersGet()
    {
        $request = new InternalRequest(['test' => 'foo']);
        static::assertSame('foo', $request->require('test'));
        static::assertSame('foo', $request->optional('test'));
    }

    public function testConsidersPost()
    {
        $request = new InternalRequest([], ['test' => 'foo']);
        static::assertSame('foo', $request->require('test'));
        static::assertSame('foo', $request->optional('test'));
    }

    public function testConsidersRouting()
    {
        $request = new InternalRequest([], [], ['test' => 'foo']);
        static::assertSame('foo', $request->require('test'));
        static::assertSame('foo', $request->optional('test'));
    }

    public function testOptionalDefault()
    {
        $request = new InternalRequest();
        static::assertSame('foo', $request->optional('test', 'foo'));
    }

    public function testArrayAccess()
    {
        $request = new InternalRequest([
            'test' => [
                'foo' => 'bar',
            ],
            'foo' => [
                'bar' => [
                    'baz' => true,
                ],
            ],
            'foo.test.bar' => 1,
        ]);
        static::assertSame('bar', $request->require('test.foo'));
        static::assertSame(['foo' => 'bar'], $request->require('test'));
        static::assertTrue($request->require('foo.bar.baz'));
        static::assertSame(1, $request->require('foo.test.bar'));

        static::assertSame('bar', $request->optional('test.foo'));
        static::assertSame(['foo' => 'bar'], $request->optional('test'));
        static::assertTrue($request->optional('foo.bar.baz'));
        static::assertSame(1, $request->optional('foo.test.bar'));
    }

    public function testCreateFromRequest()
    {
        $http = new Request(['foo' => 'bar'], ['bar' => 'foo'], ['_route_params' => ['baz' => true]]);

        $internal = InternalRequest::createFromHttpRequest($http);

        static::assertSame(['foo' => 'bar'], $internal->getQuery());
        static::assertSame(['bar' => 'foo'], $internal->getPost());
        static::assertSame(['baz' => true], $internal->getRouting());
    }
}
