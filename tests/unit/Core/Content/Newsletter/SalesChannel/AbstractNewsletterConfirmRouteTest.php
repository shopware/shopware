<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterConfirmRoute;
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
 * Covers what a decorating extension has to implement, and what it must not implement, while
 * confirm() is deprecated and confirmWithResponse() is on its way to becoming abstract.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AbstractNewsletterConfirmRoute::class)]
class AbstractNewsletterConfirmRouteTest extends TestCase
{
    private RequestDataBag $dataBag;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->dataBag = new RequestDataBag(['email' => 'test@example.com', 'option' => 'direct']);
        $this->context = static::createStub(SalesChannelContext::class);
    }

    // @deprecated tag:v6.8.0 - remove with confirm()
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfirmWithResponseFallsBackToConfirm(): void
    {
        $route = new ConfirmLegacyDecorator();

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        // The route attribute sits on confirmWithResponse(), so this is what the shop answers:
        // an extension that implements only confirm() keeps that installation on 204 with an empty body.
        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
    }

    // @deprecated tag:v6.8.0 - remove with confirm()
    public function testFallbackToConfirmThrowsWhenV680IsActive(): void
    {
        $route = new ConfirmLegacyDecorator();

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "confirmWithResponse()" will be abstract in v6.8.0.0. Override it in %s, as the "confirm()" method will be removed.',
            ConfirmLegacyDecorator::class
        )));

        $route->confirmWithResponse($this->dataBag, $this->context);
    }

    /**
     * A decorator that overrides confirmWithResponse() answers the route with its own response.
     */
    public function testOverriddenConfirmWithResponseIsUsed(): void
    {
        $route = new ConfirmWideDecorator();

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(SuccessResponse::class, $response);
    }

    /**
     * An override may declare the concrete response class instead of StoreApiResponse. Both
     * spellings have to keep working, otherwise one extension release cannot serve 6.7 and 6.8.
     */
    public function testOverrideMayDeclareTheConcreteResponseClass(): void
    {
        $route = new ConfirmExactDecorator();

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['success' => true], $response->getObject()->getVars());
    }

    // @deprecated tag:v6.8.0 - remove with confirm()
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFallbackReachesAnInnerConfirmWithResponseOverride(): void
    {
        $inner = new ConfirmWideDecorator();
        $route = new ConfirmForwardingLegacyDecorator($inner);

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
        static::assertSame(1, $inner->calls, 'the decorator below must still run');
    }

    /**
     * @deprecated tag:v6.8.0 - remove with confirm()
     *
     * A decorator that implements only confirm() may call its own route again while that call is
     * running, the way an event subscriber mirroring the operation would.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFallbackAllowsReEnteringTheRoute(): void
    {
        $inner = new ConfirmLegacyDecorator();
        $route = new ConfirmReEnteringLegacyDecorator($inner);

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(2, $inner->calls, 'both the original and the mirrored call must reach the decorated route');
    }

    /**
     * PHP only accepts an override whose return type matches the declared one or narrows it. This
     * is the whole reason confirmWithResponse() keeps StoreApiResponse in v6.8.0.0: had it been narrowed,
     * every extension declaring the wide type would fail to load, so no single extension release
     * could cover 6.7 and 6.8.
     *
     * @param class-string<AbstractNewsletterConfirmRoute> $decorator
     * @param class-string<StoreApiResponse<covariant \Shopware\Core\Framework\Struct\Struct>> $announcedType
     */
    #[DataProvider('announcedReturnTypeProvider')]
    public function testOverrideReturnTypeMustBeCompatibleWithTheDeclaredOne(string $decorator, string $announcedType, bool $loads): void
    {
        $returnType = (new \ReflectionMethod($decorator, 'confirmWithResponse'))->getReturnType();

        static::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        static::assertSame($loads, is_a($returnType->getName(), $announcedType, true));
    }

    public static function announcedReturnTypeProvider(): \Generator
    {
        yield 'announcing StoreApiResponse accepts an extension that declares the wide type' => [
            'decorator' => ConfirmWideDecorator::class,
            'announcedType' => StoreApiResponse::class,
            'loads' => true,
        ];

        yield 'announcing StoreApiResponse accepts an extension that declares the concrete type' => [
            'decorator' => ConfirmExactDecorator::class,
            'announcedType' => StoreApiResponse::class,
            'loads' => true,
        ];

        yield 'announcing the concrete type would reject an extension that declares the wide type' => [
            'decorator' => ConfirmWideDecorator::class,
            'announcedType' => SuccessResponse::class,
            'loads' => false,
        ];

        yield 'announcing the concrete type only accepts an extension that declares it as well' => [
            'decorator' => ConfirmExactDecorator::class,
            'announcedType' => SuccessResponse::class,
            'loads' => true,
        ];
    }
}

/**
 * An extension written before v6.7 that never heard about confirmWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class ConfirmLegacyDecorator extends AbstractNewsletterConfirmRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        ++$this->calls;

        return new NoContentResponse();
    }
}

/**
 * An extension that declares the widened return type on confirmWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class ConfirmWideDecorator extends AbstractNewsletterConfirmRoute
{
    public int $calls = 0;

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        ++$this->calls;

        return new SuccessResponse();
    }

    /**
     * Still required in 6.7. It has to answer with NoContentResponse, exactly as the core route
     * does: an older decorator above this one declares that as its own return type, so handing it
     * the response from confirmWithResponse() would be a TypeError inside that extension. Removed in 6.8,
     * where the method is gone from the abstract class.
     */
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        $this->confirmWithResponse($dataBag, $context);

        return new NoContentResponse();
    }
}

/**
 * An extension that names the concrete response class on confirmWithResponse().
 *
 * @internal
 */
#[Package('after-sales')]
class ConfirmExactDecorator extends AbstractNewsletterConfirmRoute
{
    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function confirmWithResponse(RequestDataBag $dataBag, SalesChannelContext $context): SuccessResponse
    {
        return new SuccessResponse();
    }

    /**
     * Still required in 6.7. It has to answer with NoContentResponse, exactly as the core route
     * does: an older decorator above this one declares that as its own return type, so handing it
     * the response from confirmWithResponse() would be a TypeError inside that extension. Removed in 6.8,
     * where the method is gone from the abstract class.
     */
    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        $this->confirmWithResponse($dataBag, $context);

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
class ConfirmForwardingLegacyDecorator extends AbstractNewsletterConfirmRoute
{
    public function __construct(private readonly AbstractNewsletterConfirmRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        return $this->decorated;
    }

    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): NoContentResponse
    {
        $response = $this->decorated->confirm($dataBag, $context);

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
class ConfirmReEnteringLegacyDecorator extends AbstractNewsletterConfirmRoute
{
    private bool $mirrored = false;

    public function __construct(private readonly AbstractNewsletterConfirmRoute $decorated)
    {
    }

    public function getDecorated(): AbstractNewsletterConfirmRoute
    {
        return $this->decorated;
    }

    public function confirm(RequestDataBag $dataBag, SalesChannelContext $context): StoreApiResponse
    {
        $response = $this->decorated->confirm($dataBag, $context);

        if (!$this->mirrored) {
            $this->mirrored = true;
            $this->confirmWithResponse($dataBag, $context);
        }

        return $response;
    }
}
