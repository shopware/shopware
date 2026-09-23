<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * Covers what a decorating extension implements while unsubscribe() is deprecated and
 * unsubscribeWithResponse() is on its way to becoming abstract.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AbstractNewsletterUnsubscribeRoute::class)]
class AbstractNewsletterUnsubscribeRouteTest extends TestCase
{
    private RequestDataBag $dataBag;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->dataBag = new RequestDataBag(['email' => 'test@example.com', 'option' => 'direct']);
        $this->context = static::createStub(SalesChannelContext::class);
    }

    /**
     * @deprecated tag:v6.8.0 - remove with unsubscribe()
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsubscribeWithResponseFallsBackToUnsubscribe(): void
    {
        $route = new UnsubscribeLegacyDecorator();

        $response = $route->unsubscribeWithResponse($this->dataBag, $this->context);

        // The route attribute sits on unsubscribeWithResponse(), so this is what the shop answers:
        // an extension that implements only unsubscribe() keeps that installation on 204 with an empty body.
        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
    }

    /**
     * @deprecated tag:v6.8.0 - remove with unsubscribe()
     */
    public function testFallbackToUnsubscribeThrowsWhenV680IsActive(): void
    {
        $route = new UnsubscribeLegacyDecorator();

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "unsubscribeWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "unsubscribe()" method will be removed.',
            UnsubscribeLegacyDecorator::class
        )));

        $route->unsubscribeWithResponse($this->dataBag, $this->context);
    }

    /**
     * A decorator that overrides unsubscribeWithResponse() answers the route with its own response.
     */
    public function testOverriddenUnsubscribeWithResponseIsUsed(): void
    {
        $route = new UnsubscribeWideDecorator();

        $response = $route->unsubscribeWithResponse($this->dataBag, $this->context);

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(SuccessResponse::class, $response);
    }

    /**
     * An override may declare the concrete response class instead of StoreApiResponse, so one
     * extension release serves 6.7 and 6.8 whichever spelling it picks.
     */
    public function testOverrideMayDeclareTheConcreteResponseClass(): void
    {
        $route = new UnsubscribeExactDecorator();

        $response = $route->unsubscribeWithResponse($this->dataBag, $this->context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['success' => true], $response->getObject()->getVars());
    }

    /**
     * @deprecated tag:v6.8.0 - remove with unsubscribe()
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFallbackReachesAnInnerUnsubscribeWithResponseOverride(): void
    {
        $inner = new UnsubscribeWideDecorator();
        $route = new UnsubscribeForwardingLegacyDecorator($inner);

        $response = $route->unsubscribeWithResponse($this->dataBag, $this->context);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
        static::assertSame(1, $inner->calls, 'the decorator below must still run');
    }
}

/**
 * An extension written before v6.7 that never heard about unsubscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class UnsubscribeLegacyDecorator extends AbstractNewsletterUnsubscribeRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        ++$this->calls;

        return new NoContentResponse();
    }
}

/**
 * An extension that declares the widened return type on unsubscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class UnsubscribeWideDecorator extends AbstractNewsletterUnsubscribeRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        ++$this->calls;

        return new SuccessResponse();
    }

    /**
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
     */
    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        $this->unsubscribeWithResponse($dataBag, $context);

        return new NoContentResponse();
    }
}

/**
 * An extension that names the concrete response class on unsubscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class UnsubscribeExactDecorator extends AbstractNewsletterUnsubscribeRoute
{
    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function unsubscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return new SuccessResponse();
    }

    /**
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
     */
    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        $this->unsubscribeWithResponse($dataBag, $context);

        return new NoContentResponse();
    }
}

/**
 * An untouched pre-6.7 decorator: it implements only the deprecated method and forwards it down
 * the chain, declaring the return type the old contract allowed.
 *
 * @internal
 */
#[Package('after-sales')]
class UnsubscribeForwardingLegacyDecorator extends AbstractNewsletterUnsubscribeRoute
{
    public function __construct(private readonly AbstractNewsletterUnsubscribeRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNewsletterUnsubscribeRoute
    {
        return $this->decorated;
    }

    public function unsubscribe(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse
    {
        $response = $this->decorated->unsubscribe($dataBag, $context);

        \assert($response instanceof NoContentResponse);

        return $response;
    }
}
