<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Foo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseHelper\AssertResponseHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class BarTest extends TestCase
{
    public function testFoo(): void
    {
        $response = new Response();

        $expected = new Response();
        // not allowed
        static::assertEquals($expected, $response);

        // Allowed as `assertSame` checks for the same reference, not the actual contents of the object
        static::assertSame($expected, $response);

        $this->assertFoo($expected, $response);

        // allowed
        static::assertSame($expected->getStatusCode(), $response->getStatusCode());

        // allowed
        AssertResponseHelper::assertResponseEquals($expected, $response);

        // allowed
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRedirects(): void
    {
        $response = new RedirectResponse('foo');

        $expected = new RedirectResponse('bar');

        // not allowed
        static::assertEquals($expected, $response);
    }

    public function assertFoo(mixed $expected, mixed $actual): void
    {
        // allowed
        static::assertSame($expected, $actual);
    }

    public function doesNotAssertNeverType(Response $foo): void
    {
        $bar = new \stdClass();
        \assert($bar instanceof Response);

        static::assertSame($bar, $foo);
    }
}
