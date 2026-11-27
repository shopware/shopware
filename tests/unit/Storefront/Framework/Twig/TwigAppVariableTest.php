<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\StorefrontFrameworkException;
use Shopware\Storefront\Framework\Twig\TwigAppVariable;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TwigAppVariable::class)]
class TwigAppVariableTest extends TestCase
{
    private RequestStack $requestStack;

    private TwigAppVariable $appVariable;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();

        $inner = new AppVariable();
        $inner->setRequestStack($this->requestStack);

        $this->appVariable = new TwigAppVariable($inner, ['good']);
    }

    public function testRequestGetsCloned(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        static::assertNotSame($request, $this->appVariable->getRequest());
    }

    public function testClonedRequestLosesServerVarsNotOnAllowList(): void
    {
        $request = new Request(server: ['good' => '1', 'bad' => '1']);
        $this->requestStack->push($request);

        $appRequest = $this->appVariable->getRequest();

        static::assertNotNull($appRequest);
        static::assertTrue($appRequest->server->has('good'));
        static::assertFalse($appRequest->server->has('bad'));
        static::assertTrue($request->server->has('good'));
        static::assertTrue($request->server->has('bad'));
    }

    public function testSameRequestReturnsSameClone(): void
    {
        $this->requestStack->push(new Request());

        static::assertSame($this->appVariable->getRequest(), $this->appVariable->getRequest());
    }

    public function testNextRequestInLongRunningWorkerIsNotTheFirstOne(): void
    {
        $this->requestStack->push(Request::create('http://music.localhost/admin'));
        static::assertSame('music.localhost', $this->currentRequest()->getHost());
        $this->requestStack->pop();

        $this->requestStack->push(Request::create('http://localhost/admin'));
        static::assertSame('localhost', $this->currentRequest()->getHost());
    }

    public function testSubRequestSeesItsOwnRequestAndMainRequestIsRestoredAfterwards(): void
    {
        $this->requestStack->push(new Request(attributes: ['_route' => 'frontend.detail.page']));
        static::assertSame('frontend.detail.page', $this->currentRequest()->attributes->get('_route'));

        $this->requestStack->push(new Request(attributes: ['_route' => 'frontend.header']));
        static::assertSame('frontend.header', $this->currentRequest()->attributes->get('_route'));

        $this->requestStack->pop();
        static::assertSame('frontend.detail.page', $this->currentRequest()->attributes->get('_route'));
    }

    public function testThrowsWhenNoRequestIsAvailable(): void
    {
        $this->expectExceptionObject(StorefrontFrameworkException::appRequestNotAvailable());

        $this->appVariable->getRequest();
    }

    public function testThrowsWhenRequestIsGoneAfterAnEarlierRequest(): void
    {
        $this->requestStack->push(new Request());
        $this->appVariable->getRequest();
        $this->requestStack->pop();

        $this->expectExceptionObject(StorefrontFrameworkException::appRequestNotAvailable());

        $this->appVariable->getRequest();
    }

    private function currentRequest(): Request
    {
        $request = $this->appVariable->getRequest();
        static::assertNotNull($request);

        return $request;
    }
}
