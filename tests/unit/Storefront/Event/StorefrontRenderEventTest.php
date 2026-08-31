<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontRenderEvent::class)]
class StorefrontRenderEventTest extends TestCase
{
    public function testParametersAreMergedWithTheDefaults(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $event = new StorefrontRenderEvent(
            '@Storefront/storefront/page/content/index.html.twig',
            ['page' => 'content'],
            new Request(),
            $salesChannelContext,
        );

        static::assertSame(
            [
                'context' => $salesChannelContext,
                'headerParameters' => [],
                'footerParameters' => [],
                'page' => 'content',
            ],
            $event->getParameters()
        );
    }

    public function testPassedParametersOverrideTheDefaults(): void
    {
        $event = new StorefrontRenderEvent(
            'index.html.twig',
            ['headerParameters' => ['navigationId' => 'foo']],
            new Request(),
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(['navigationId' => 'foo'], $event->getParameter('headerParameters'));
    }

    public function testGetParameterReturnsNullForUnknownKeys(): void
    {
        $event = new StorefrontRenderEvent('index.html.twig', [], new Request(), Generator::generateSalesChannelContext());

        static::assertNull($event->getParameter('unknown'));
    }

    public function testSetParameterAddsToTheParameters(): void
    {
        $event = new StorefrontRenderEvent('index.html.twig', [], new Request(), Generator::generateSalesChannelContext());

        $event->setParameter('page', 'content');

        static::assertSame('content', $event->getParameter('page'));
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $event = new StorefrontRenderEvent('index.html.twig', [], $request, $salesChannelContext);

        static::assertSame('index.html.twig', $event->getView());
        static::assertSame($request, $event->getRequest());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
    }

    public function testSetSalesChannelContextReplacesTheContext(): void
    {
        $event = new StorefrontRenderEvent('index.html.twig', [], new Request(), Generator::generateSalesChannelContext());

        $replacement = Generator::generateSalesChannelContext();
        $event->setSalesChannelContext($replacement);

        static::assertSame($replacement, $event->getSalesChannelContext());
    }
}
