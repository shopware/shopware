<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelInlineImage;
use Shopware\Core\Content\Product\Garan\GaranLabelMailSubscriber;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelMailSubscriber::class)]
class GaranLabelMailSubscriberTest extends TestCase
{
    private const TEMPLATE = '{% set garanLabel = garanLabels[nestedItem.productId] ?? null %}';

    public function testSubscribesToMailEvents(): void
    {
        static::assertSame(
            [
                MailBeforeValidateEvent::class => 'addLabels',
                MailBeforeSentEvent::class => 'embedLabelImages',
            ],
            GaranLabelMailSubscriber::getSubscribedEvents()
        );
    }

    public function testAddsTheLabelsOfAllProductsKeyedByProductIdWithOneSearch(): void
    {
        $event = $this->createEvent(['product-a', 'product-b', null, 'product-a']);

        $this->createSubscriber([
            static function (Criteria $criteria): ProductCollection {
                static::assertSame(['product-a', 'product-b'], $criteria->getIds(), 'each product once, line items without product skipped');
                static::assertTrue($criteria->hasAssociation('manufacturer'), 'the label needs the brand');

                return new ProductCollection([
                    self::createProduct('product-a', guaranteeConfirmed: true),
                    self::createProduct('product-b', guaranteeConfirmed: true, guaranteeMonths: 30),
                ]);
            },
            static fn () => static::fail('cid and duration have to come from a single product search'),
        ])->addLabels($event);

        static::assertSame(
            [
                'product-a' => ['cid' => 'cid:garan-label-nested-36.png', 'duration' => '3'],
                'product-b' => ['cid' => 'cid:garan-label-nested-30.png', 'duration' => '2,5'],
            ],
            $event->getTemplateData()['garanLabels']
        );
    }

    public function testLoadsProductsWithInheritanceSoVariantsGetTheirParentsLabel(): void
    {
        $event = $this->createEvent(['product-id']);
        static::assertFalse($event->getContext()->considerInheritance(), 'admin-triggered mails come without inheritance');

        $this->createSubscriber([
            static function (Criteria $criteria, Context $context): ProductCollection {
                static::assertTrue($context->considerInheritance());

                return new ProductCollection([self::createProduct('product-id', guaranteeConfirmed: true)]);
            },
        ])->addLabels($event);

        static::assertArrayHasKey('product-id', $event->getTemplateData()['garanLabels']);
        static::assertFalse($event->getContext()->considerInheritance(), 'the mail context is left as it was');
    }

    public function testKeepsTheDurationWithoutPreRenderedImage(): void
    {
        $event = $this->createEvent(['product-id']);

        $this->createSubscriber([
            new ProductCollection([self::createProduct('product-id', guaranteeConfirmed: true, guaranteeMonths: 606)]),
        ])->addLabels($event);

        static::assertSame(
            ['product-id' => ['cid' => null, 'duration' => '50,5']],
            $event->getTemplateData()['garanLabels'],
            'Legacy durations above the maximum have no image, the template falls back to the duration text'
        );
    }

    public function testSkipsProductsWithoutConfirmedGuarantee(): void
    {
        $event = $this->createEvent(['product-id']);

        $this->createSubscriber([
            new ProductCollection([self::createProduct('product-id', guaranteeConfirmed: false)]),
        ])->addLabels($event);

        static::assertSame([], $event->getTemplateData()['garanLabels']);
    }

    public function testAddsTheLabelsForThePlainTextTemplate(): void
    {
        $event = new MailBeforeValidateEvent(
            ['contentHtml' => '<p>Order</p>', 'contentPlain' => self::TEMPLATE],
            Context::createDefaultContext(),
            ['order' => self::createOrder(['product-id'])],
        );

        $this->createSubscriber([
            new ProductCollection([self::createProduct('product-id', guaranteeConfirmed: true)]),
        ])->addLabels($event);

        static::assertArrayHasKey('product-id', $event->getTemplateData()['garanLabels']);
    }

    /**
     * @return \Generator<string, array{string, array<string, mixed>}>
     */
    public static function mailWithoutLabelsProvider(): \Generator
    {
        yield 'template without labels' => ['<p>{{ order.orderNumber }}</p>', ['order' => self::createOrder(['product-id'])]];
        yield 'no order entity' => [self::TEMPLATE, ['order' => ['lineItems' => [['productId' => 'product-id']]]]];
        yield 'no order' => [self::TEMPLATE, []];
        yield 'no product line items' => [self::TEMPLATE, ['order' => self::createOrder([null])]];
    }

    /**
     * @param array<string, mixed> $templateData
     */
    #[DataProvider('mailWithoutLabelsProvider')]
    public function testAddsNoLabelsAndDoesNotSearch(string $template, array $templateData): void
    {
        $event = new MailBeforeValidateEvent(['contentHtml' => $template], Context::createDefaultContext(), $templateData);

        $this->createSubscriber([
            static fn () => static::fail('mails that cannot show a label must not load products'),
        ])->addLabels($event);

        static::assertArrayNotHasKey('garanLabels', $event->getTemplateData());
    }

    public function testEmbedsReferencedLabelAsInlineImage(): void
    {
        $email = $this->createEmail('<p>Product</p><img src="cid:garan-label-nested-36.png" alt="GARAN">');

        $this->dispatch($email);

        $attachments = $email->getAttachments();
        static::assertCount(1, $attachments);

        $part = $attachments[0];
        static::assertSame('garan-label-nested-36.png', $part->getName());
        static::assertSame('image/png', $part->getMediaType() . '/' . $part->getMediaSubtype());

        $html = $email->getBody()->bodyToString();
        static::assertStringContainsString('Content-Disposition: inline', $html);
        static::assertStringContainsString('cid:' . $part->getContentId(), $html, 'Symfony Mime has to link the reference to the attached part');
        static::assertStringNotContainsString('cid:garan-label-nested-36.png', $html);
    }

    public function testEmbedsEachLabelOnlyOnce(): void
    {
        $email = $this->createEmail(
            '<img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-30.png">'
        );

        $this->dispatch($email);
        $this->dispatch($email);

        static::assertSame(
            ['garan-label-nested-36.png', 'garan-label-nested-30.png'],
            array_map(static fn (DataPart $part): ?string => $part->getName(), $email->getAttachments())
        );
    }

    public function testIgnoresReferencesWithoutPreRenderedImage(): void
    {
        $email = $this->createEmail('<img src="cid:garan-label-nested-999.png"><img src="cid:logo.png">');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    public function testIgnoresMailsWithoutHtmlBody(): void
    {
        $email = (new Email())->text('cid:garan-label-nested-36.png');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    private function createEmail(string $html): Email
    {
        return (new Email())
            ->from('shop@example.com')
            ->to('customer@example.com')
            ->subject('Order confirmation')
            ->text('Order confirmation')
            ->html($html);
    }

    private function dispatch(Email $email): void
    {
        $this->createSubscriber([])
            ->embedLabelImages(new MailBeforeSentEvent([], $email, Context::createDefaultContext()));
    }

    /**
     * @param list<string|null> $productIds
     */
    private function createEvent(array $productIds): MailBeforeValidateEvent
    {
        return new MailBeforeValidateEvent(
            ['contentHtml' => self::TEMPLATE],
            Context::createDefaultContext(),
            ['order' => self::createOrder($productIds)],
        );
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createSubscriber(array $searchResults): GaranLabelMailSubscriber
    {
        $resolver = new GaranLabelResolver(
            new GaranLabelDurationFormatter(),
            new GaranLabelRenderer(static::createStub(Environment::class)),
        );

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository($searchResults, new ProductDefinition());

        return new GaranLabelMailSubscriber(new GaranLabelInlineImage(), $productRepository, $resolver);
    }

    /**
     * @param list<string|null> $productIds
     */
    private static function createOrder(array $productIds): OrderEntity
    {
        $lineItems = new OrderLineItemCollection();

        foreach ($productIds as $index => $productId) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId('line-item-' . $index);
            $lineItem->setProductId($productId);
            $lineItems->add($lineItem);
        }

        $order = new OrderEntity();
        $order->setLineItems($lineItems);

        return $order;
    }

    private static function createProduct(string $id, bool $guaranteeConfirmed, int $guaranteeMonths = 36): ProductEntity
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setName('ACME');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $product = new ProductEntity();
        $product->setId($id);
        $product->setManufacturer($manufacturer);
        $product->setManufacturerNumber('ACME-123');
        $product->setGuaranteeMonths($guaranteeMonths);
        $product->setGuaranteeConfirmed($guaranteeConfirmed);

        return $product;
    }
}
