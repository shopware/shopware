<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Event\ProductExportRenderBodyContextEvent;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Content\ProductExport\Provider\AbstractProductExportProvider;
use Shopware\Core\Content\ProductExport\Provider\ProductExportProviderRegistry;
use Shopware\Core\Content\ProductExport\Subscriber\ProductExportProviderContextSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductExportProviderContextSubscriber::class)]
class ProductExportProviderContextSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [ProductExportRenderBodyContextEvent::class => 'extendBodyContext'],
            ProductExportProviderContextSubscriber::getSubscribedEvents()
        );
    }

    public function testExtendBodyContextAddsProviderSpecificContext(): void
    {
        $subscriber = new ProductExportProviderContextSubscriber(
            new ProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setProvider('open-ai');

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertSame('open-ai', $event->getContext()['providerKey']);
        static::assertSame($productExport, $event->getContext()['productExport']);
        static::assertSame($salesChannelContext, $event->getContext()['context']);
    }

    public function testExtendBodyContextDoesNothingWithoutProviderKey(): void
    {
        $subscriber = new ProductExportProviderContextSubscriber(
            new ProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertArrayNotHasKey('providerKey', $event->getContext());
    }

    public function testExtendBodyContextDoesNothingWhenContextIsIncomplete(): void
    {
        $subscriber = new ProductExportProviderContextSubscriber(
            new ProductExportProviderRegistry([
                $this->createProvider(),
            ])
        );

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => new ProductExportEntity(),
        ]);

        $subscriber->extendBodyContext($event);

        static::assertSame(['productExport' => $event->getContext()['productExport']], $event->getContext());
    }

    public function testExtendBodyContextDoesNothingWhenProviderIsNotRegistered(): void
    {
        $subscriber = new ProductExportProviderContextSubscriber(
            new ProductExportProviderRegistry([])
        );

        $productExport = new ProductExportEntity();
        $productExport->setId(Uuid::randomHex());
        $productExport->setProvider('open-ai');

        $salesChannelContext = $this->createSalesChannelContext();

        $event = new ProductExportRenderBodyContextEvent([
            'productExport' => $productExport,
            'context' => $salesChannelContext,
        ]);

        $subscriber->extendBodyContext($event);

        static::assertArrayNotHasKey('providerKey', $event->getContext());
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        return Generator::generateSalesChannelContext(
            baseContext: Context::createDefaultContext(),
            salesChannel: $salesChannel
        );
    }

    private function createProvider(): AbstractProductExportProvider
    {
        return new class('open-ai') extends AbstractProductExportProvider {
            public function __construct(private readonly string $technicalName)
            {
            }

            public function getTechnicalName(): string
            {
                return $this->technicalName;
            }

            public function extendRenderContext(
                ProductExportEntity $productExport,
                SalesChannelContext $salesChannelContext,
                array $renderContext
            ): array {
                $renderContext['providerKey'] = $this->technicalName;

                return $renderContext;
            }
        };
    }
}
