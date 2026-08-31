<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Covers what a decorating extension implements while subscribe() is deprecated and
 * subscribeWithResponse() is on its way to becoming abstract.
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

    /**
     * @deprecated tag:v6.8.0 - remove with subscribe()
     */
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

    /**
     * @deprecated tag:v6.8.0 - remove with subscribe()
     */
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
     * An override may declare the concrete response class instead of StoreApiResponse, so one
     * extension release serves 6.7 and 6.8 whichever spelling it picks.
     */
    public function testOverrideMayDeclareTheConcreteResponseClass(): void
    {
        $route = new ExactDecorator();

        $response = $route->subscribeWithResponse($this->dataBag, $this->context, false);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(NewsletterSubscribeRoute::STATUS_DIRECT, $response->getStatus());
    }

    /**
     * @deprecated tag:v6.8.0 - remove with subscribe()
     */
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
     * A decorator that does not override subscribeWithResponse() inherits a signature without a
     * default for $validateStorefrontUrl. The route default supplies the value, which keeps the
     * parameter optional for every decorator shape without touching the abstract signature.
     */
    public function testRouteSuppliesTheArgumentADecoratorDoesNotDefault(): void
    {
        $attributes = (new \ReflectionMethod(NewsletterSubscribeRoute::class, 'subscribeWithResponse'))
            ->getAttributes(Route::class);

        static::assertCount(1, $attributes);

        $request = new Request();
        // The router adds the route defaults to the request attributes before argument resolution.
        $request->attributes->add($attributes[0]->newInstance()->defaults);
        // In a request these two come from Shopware's own resolvers; only the third one is at stake.
        $request->attributes->set('dataBag', $this->dataBag);
        $request->attributes->set('context', $this->context);

        $resolver = new ArgumentResolver(
            new ArgumentMetadataFactory(),
            [new RequestAttributeValueResolver(), new DefaultValueResolver()]
        );

        $decorator = new LegacyDecorator();

        $arguments = $resolver->getArguments($request, $decorator->subscribeWithResponse(...));

        static::assertSame([$this->dataBag, $this->context, true], $arguments);
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
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
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
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
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
