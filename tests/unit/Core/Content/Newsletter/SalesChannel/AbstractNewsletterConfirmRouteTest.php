<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
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
 * Covers what a decorating extension implements while confirm() is deprecated and
 * confirmWithResponse() is on its way to becoming abstract.
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

    /**
     * @deprecated tag:v6.8.0 - remove with confirm()
     */
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

    /**
     * @deprecated tag:v6.8.0 - remove with confirm()
     */
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
     * An override may declare the concrete response class instead of StoreApiResponse, so one
     * extension release serves 6.7 and 6.8 whichever spelling it picks.
     */
    public function testOverrideMayDeclareTheConcreteResponseClass(): void
    {
        $route = new ConfirmExactDecorator();

        $response = $route->confirmWithResponse($this->dataBag, $this->context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['success' => true], $response->getObject()->getVars());
    }

    /**
     * @deprecated tag:v6.8.0 - remove with confirm()
     */
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
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
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
     * Still required in 6.7, and it answers with NoContentResponse exactly as the core route does,
     * which is the return type an older decorator above this one declares. Removed in 6.8, where
     * the method is gone from the abstract class.
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
