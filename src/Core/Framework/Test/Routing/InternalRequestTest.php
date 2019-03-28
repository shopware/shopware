<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Routing\InternalRequest;
use Symfony\Component\HttpFoundation\Request;

class InternalRequestTest extends TestCase
{
    public function testRequireGetThrowsException(): void
    {
        $request = new InternalRequest();
        $this->expectException(MissingRequestParameterException::class);

        $request->requireGet('test');
    }

    public function testRequirePostThrowsException(): void
    {
        $request = new InternalRequest();
        $this->expectException(MissingRequestParameterException::class);

        $request->requirePost('test');
    }

    public function testRequireRoutingThrowsException(): void
    {
        $request = new InternalRequest();
        $this->expectException(MissingRequestParameterException::class);

        $request->requireRouting('test');
    }

    public function testRequiresGetConsidersRouting(): void
    {
        $request = new InternalRequest(
            [],
            [],
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requireGet('foo'));
    }

    public function testRequiresPostSuccess(): void
    {
        $request = new InternalRequest(
            [],
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requirePost('foo'));
    }

    public function testRequiresGetSuccess(): void
    {
        $request = new InternalRequest(
            ['foo' => 'bar']
        );

        static::assertSame('bar', $request->requireGet('foo'));
    }

    public function testOptionPostDefault(): void
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalPost('test', 'bar'));
    }

    public function testOptionGetDefault(): void
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalGet('test', 'bar'));
    }

    public function testOptionRoutingDefault(): void
    {
        $request = new InternalRequest([], [], []);
        static::assertSame('bar', $request->optionalRouting('test', 'bar'));
    }

    public function testCreateFromRequest(): void
    {
        $http = new Request(['foo' => 'bar'], ['bar' => 'foo'], ['_route_params' => ['baz' => true]]);

        $internal = InternalRequest::createFromHttpRequest($http);

        static::assertSame(['foo' => 'bar'], $internal->getGet());
        static::assertSame(['bar' => 'foo'], $internal->getPost());
        static::assertSame(['baz' => true], $internal->getRouting());
    }
}
