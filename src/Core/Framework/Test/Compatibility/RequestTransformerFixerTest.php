<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Compatibility;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Compatibility\RequestTransformerFixer;
use Shopware\Core\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

class RequestTransformerFixerTest extends TestCase
{
    /**
     * @dataProvider prefixedHeaderOptions
     */
    public function testForwardedPrefixHeader(callable $requestFunc, bool $assert): void
    {
        /** @var Request $request */
        $request = $requestFunc();

        $innerTransformer = $this->createMock(RequestTransformer::class);
        $innerTransformer->method('transform')->willReturn($request);

        $newRequest = (new RequestTransformerFixer($innerTransformer))->transform($request);

        static::assertSame($assert, $newRequest->headers->has('x-forwarded-prefix'));
    }

    public function prefixedHeaderOptions(): iterable
    {
        yield 'Request without x-prefix-header' => [
            function () {
                return new Request();
            },
            false,
        ];

        yield 'Request with x-prefix-header without trusted should be removed' => [
            function () {
                $r = new Request();
                $r->headers->set('x-forwarded-prefix', 'test');

                return $r;
            },
            false,
        ];

        yield 'Request with x-prefix-header with trusted should but not allowed for prefix' => [
            function () {
                Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_HOST);
                $r = new Request();
                $r->headers->set('x-forwarded-prefix', 'test');

                return $r;
            },
            false,
        ];

        yield 'Request with x-prefix-header with trusted allowed' => [
            function () {
                Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_PREFIX);
                $r = new Request();
                $r->server->set('REMOTE_ADDR', '127.0.0.1');
                $r->headers->set('x-forwarded-prefix', 'test');

                return $r;
            },
            true,
        ];
    }
}
