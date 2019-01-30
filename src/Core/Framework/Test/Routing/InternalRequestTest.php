<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\HttpFoundation\Request;

class InternalRequestTest extends TestCase
{
    public function testRequireGetThrowsException()
    {
        $request = new InternalRequest();
        $this->expectException(MissingParameterException::class);

        $request->requireGet('test');
    }

    public function testRequirePostThrowsException()
    {
        $request = new InternalRequest();
        $this->expectException(MissingParameterException::class);

        $request->requirePost('test');
    }

    public function testRequireRoutingThrowsException()
    {
        $request = new InternalRequest();
        $this->expectException(MissingParameterException::class);

        $request->requireRouting('test');
    }

    public function testRequiresGetConsidersRouting()
    {
        $request = new InternalRequest(
            [],
            [],
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requireGet('foo'));
    }

    public function testRequiresPostSuccess()
    {
        $request = new InternalRequest(
            [],
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requirePost('foo'));
    }

    public function testRequiresGetSuccess()
    {
        $request = new InternalRequest(
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requireGet('foo'));
    }

    public function testOptionPostDefault()
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalPost('test', 'bar'));
    }

    public function testOptionGetDefault()
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalGet('test', 'bar'));
    }

    public function testOptionRoutingDefault()
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalRouting('test', 'bar'));
    }

    public function testCreateFromRequest()
    {
        $http = new Request(['foo' => 'bar'], ['bar' => 'foo'], ['_route_params' => ['baz' => true]]);

        $internal = InternalRequest::createFromHttpRequest($http);

        static::assertSame(['foo' => 'bar'], $internal->getGet());
        static::assertSame(['bar' => 'foo'], $internal->getPost());
        static::assertSame(['baz' => true], $internal->getRouting());
    }
}
