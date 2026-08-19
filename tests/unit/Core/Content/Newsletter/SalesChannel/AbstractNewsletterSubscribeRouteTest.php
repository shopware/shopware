<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRouteResponse;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * Covers what a decorating extension has to implement, and what it must not implement, while
 * subscribe() is deprecated and subscribeWithResponse() is on its way to becoming abstract.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AbstractNewsletterSubscribeRoute::class)]
class AbstractNewsletterSubscribeRouteTest extends TestCase
{
    private RequestDataBag $dataBag;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->dataBag = new RequestDataBag(['email' => 'test@example.com', 'option' => 'direct']);
        $this->context = static::createStub(SalesChannelContext::class);
    }

    // @deprecated tag:v6.8.0 - remove with subscribe()
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSubscribeWithResponseFallsBackToSubscribe(): void
    {
        $route = new LegacyDecorator();

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        // The route attribute sits on subscribeWithResponse(), so this is what the shop answers:
        // an extension that implements only subscribe() keeps that installation on 204 with an empty body.
        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
    }

    // @deprecated tag:v6.8.0 - remove with subscribe()
    public function testFallbackToSubscribeThrowsWhenV680IsActive(): void
    {
        $route = new LegacyDecorator();

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "subscribeWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "subscribe()" method will be removed.',
            LegacyDecorator::class
        )));

        $route->subscribeWithResponse($this->dataBag, $this->context, false);
    }

    /**
     * A decorator that overrides subscribeWithResponse() answers the route with its own response, so the
     * shop keeps returning the status field.
     */
    public function testOverriddenSubscribeWithResponseIsUsed(): void
    {
        $route = new WideDecorator();

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(NewsletterSubscribeRouteResponse::class, $response);
        static::assertSame(NewsletterSubscribeRoute::STATUS_DIRECT, $response->getStatus());
    }

    /**
     * An override may declare the concrete response class instead of StoreApiResponse. Both
     * spellings have to keep working, otherwise one extension release cannot serve 6.7 and 6.8.
     */
    public function testOverrideMayDeclareTheConcreteResponseClass(): void
    {
        $route = new ExactDecorator();

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(NewsletterSubscribeRoute::STATUS_DIRECT, $response->getStatus());
    }

    // @deprecated tag:v6.8.0 - remove with subscribe()
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFallbackReachesAnInnerSubscribeWithResponseOverride(): void
    {
        $inner = new WideDecorator();
        $route = new ForwardingLegacyDecorator($inner);

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
        static::assertSame(1, $inner->calls, 'the decorator below must still run');
    }

    /**
     * @deprecated tag:v6.8.0 - remove with subscribe()
     *
     * A decorator that implements only subscribe() may call its own route again while that call is
     * running, the way an event subscriber mirroring the operation would.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFallbackAllowsReEnteringTheRoute(): void
    {
        $inner = new LegacyDecorator();
        $route = new ReEnteringLegacyDecorator($inner);

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(2, $inner->calls, 'both the original and the mirrored call must reach the decorated route');
    }

    /**
     * PHP only accepts an override whose return type matches the declared one or narrows it. This
     * is the whole reason subscribeWithResponse() keeps StoreApiResponse in v6.8.0.0: had it been narrowed,
     * every extension declaring the wide type would fail to load, so no single extension release
     * could cover 6.7 and 6.8.
     *
     * @param class-string<AbstractNewsletterSubscribeRoute> $decorator
     * @param class-string<StoreApiResponse<covariant \Shopware\Core\Framework\Struct\Struct>> $announcedType
     */
    #[DataProvider('announcedReturnTypeProvider')]
    public function testOverrideReturnTypeMustBeCompatibleWithTheDeclaredOne(string $decorator, string $announcedType, bool $loads): void
    {
        $returnType = (new \ReflectionMethod($decorator, 'subscribeWithResponse'))->getReturnType();

        static::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        static::assertSame($loads, is_a($returnType->getName(), $announcedType, true));
    }

    public static function announcedReturnTypeProvider(): \Generator
    {
        yield 'announcing StoreApiResponse accepts an extension that declares the wide type' => [
            'decorator' => WideDecorator::class,
            'announcedType' => StoreApiResponse::class,
            'loads' => true,
        ];

        yield 'announcing StoreApiResponse accepts an extension that declares the concrete type' => [
            'decorator' => ExactDecorator::class,
            'announcedType' => StoreApiResponse::class,
            'loads' => true,
        ];

        yield 'announcing the concrete type would reject an extension that declares the wide type' => [
            'decorator' => WideDecorator::class,
            'announcedType' => NewsletterSubscribeRouteResponse::class,
            'loads' => false,
        ];

        yield 'announcing the concrete type only accepts an extension that declares it as well' => [
            'decorator' => ExactDecorator::class,
            'announcedType' => NewsletterSubscribeRouteResponse::class,
            'loads' => true,
        ];
    }
}

/**
 * An extension written before v6.7 that never heard about subscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class LegacyDecorator extends AbstractNewsletterSubscribeRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        ++$this->calls;

        return new NoContentResponse();
    }
}

/**
 * An extension that declares the widened return type on subscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class WideDecorator extends AbstractNewsletterSubscribeRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function subscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        ++$this->calls;

        return new NewsletterSubscribeRouteResponse(NewsletterSubscribeRoute::STATUS_DIRECT);
    }

    /**
     * Still required in 6.7. It has to answer with NoContentResponse, exactly as the core route
     * does: an older decorator above this one declares that as its own return type, so handing it
     * the response from subscribeWithResponse() would be a TypeError inside that extension. Removed in 6.8,
     * where the method is gone from the abstract class.
     */
    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        $this->subscribeWithResponse($dataBag, $context, $validateStorefrontUrl);

        return new NoContentResponse();
    }
}

/**
 * An extension that names the concrete response class on subscribeWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class ExactDecorator extends AbstractNewsletterSubscribeRoute
{
    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function subscribeWithResponse(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): NewsletterSubscribeRouteResponse
    {
        return new NewsletterSubscribeRouteResponse(NewsletterSubscribeRoute::STATUS_DIRECT);
    }

    /**
     * Still required in 6.7. It has to answer with NoContentResponse, exactly as the core route
     * does: an older decorator above this one declares that as its own return type, so handing it
     * the response from subscribeWithResponse() would be a TypeError inside that extension. Removed in 6.8,
     * where the method is gone from the abstract class.
     */
    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        $this->subscribeWithResponse($dataBag, $context, $validateStorefrontUrl);

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
class ForwardingLegacyDecorator extends AbstractNewsletterSubscribeRoute
{
    public function __construct(private readonly AbstractNewsletterSubscribeRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        return $this->decorated;
    }

    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): NoContentResponse
    {
        $response = $this->decorated->subscribe($dataBag, $context, $validateStorefrontUrl);

        \assert($response instanceof NoContentResponse);

        return $response;
    }
}

/**
 * An untouched pre-6.7 decorator that calls its own route a second time while the first call is
 * still running, the way an event subscriber mirroring the operation would.
 *
 * @internal
 */
#[Package('after-sales')]
class ReEnteringLegacyDecorator extends AbstractNewsletterSubscribeRoute
{
    private bool $mirrored = false;

    public function __construct(private readonly AbstractNewsletterSubscribeRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNewsletterSubscribeRoute
    {
        return $this->decorated;
    }

    public function subscribe(RequestDataBag $dataBag, SalesChannelContext $context, bool $validateStorefrontUrl): StoreApiResponse
    {
        $response = $this->decorated->subscribe($dataBag, $context, $validateStorefrontUrl);

        if (!$this->mirrored) {
            $this->mirrored = true;
            $this->subscribeWithResponse($dataBag, $context, $validateStorefrontUrl);
        }

        return $response;
    }
}
