<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemQuantityChangedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\AccountOrderController;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class CartServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    private EntityRepository $customerRepository;

    private AccountService $accountService;

    private Connection $connection;

    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->accountService = $this->getContainer()->get(AccountService::class);

        $context = Context::createDefaultContext();
        $this->productId = Uuid::randomHex();
        $product = [
            'id' => $this->productId,
            'productNumber' => $this->productId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $context);
    }

    public function testCreateNewWithEvent(): void
    {
        $caughtEvent = null;
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), CartCreatedEvent::class, static function (CartCreatedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cartService = $this->getContainer()->get(CartService::class);

        $token = Uuid::randomHex();
        $newCart = $cartService->createNew($token);

        static::assertInstanceOf(CartCreatedEvent::class, $caughtEvent);
        static::assertSame($newCart, $caughtEvent->getCart());
        static::assertSame($newCart, $cartService->getCart($token, $this->getSalesChannelContext()));
        static::assertNotSame($newCart, $cartService->createNew($token));
    }

    public function testLineItemAddedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $isMerged = null;
        $this->addEventListener($dispatcher, BeforeLineItemAddedEvent::class, static function (BeforeLineItemAddedEvent $addedEvent) use (&$isMerged): void {
            $isMerged = $addedEvent->isMerged();
        });

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $cartId = Uuid::randomHex();
        $cart = $cartService->getCart($cartId, $context);
        $cartService->add(
            $cart,
            (new LineItem('test', 'test'))->setStackable(true),
            $context
        );

        static::assertNotNull($isMerged);
        static::assertFalse($isMerged);

        $cartService->add(
            $cart,
            new LineItem('test', 'test'),
            $context
        );

        /** @phpstan-ignore-next-line */
        static::assertTrue($isMerged);
    }

    public function testAfterLineItemAddedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, AfterLineItemAddedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $cartId = Uuid::randomHex();
        $cart = $cartService->getCart($cartId, $context);
        $cartService->add(
            $cart,
            (new LineItem('test', 'test')),
            $context
        );
    }

    public function testLineItemRemovedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, BeforeLineItemRemovedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->productId, 'referencedId' => $this->productId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->productId));

        $cart = $cartService->remove($cart, $this->productId, $context);

        static::assertFalse($cart->has($this->productId));
    }

    public function testAfterLineItemRemovedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, AfterLineItemRemovedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->productId, 'referencedId' => $this->productId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->productId));

        $cart = $cartService->remove($cart, $this->productId, $context);

        static::assertFalse($cart->has($this->productId));
    }

    public function testLineItemQuantityChangedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, BeforeLineItemQuantityChangedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->productId, 'referencedId' => $this->productId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->productId));

        $cartService->changeQuantity($cart, $this->productId, 100, $context);
    }

    public function testAfterLineItemQuantityChangedEventFired(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $this->addEventListener($dispatcher, AfterLineItemQuantityChangedEvent::class, $listener);

        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->productId, 'referencedId' => $this->productId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($this->productId));

        $cartService->changeQuantity($cart, $this->productId, 100, $context);
    }

    public function testZeroPricedItemsCanBeAddedToCart(): void
    {
        $cartService = $this->getContainer()->get(CartService::class);

        $context = $this->getSalesChannelContext();

        $productId = Uuid::randomHex();
        $product = [
            'id' => $productId,
            'productNumber' => $productId,
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 0, 'net' => 0, 'linked' => false],
            ],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 18],
            'manufacturer' => ['name' => 'test'],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$product], $context->getContext());
        $this->addTaxDataToSalesChannel($context, $product['tax']);

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $context);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        static::assertTrue($cart->has($productId));
        static::assertEquals(0, $cart->getPrice()->getTotalPrice());

        $calculatedLineItem = $cart->getLineItems()->get($productId);
        static::assertNotNull($calculatedLineItem);
        static::assertNotNull($calculatedLineItem->getPrice());
        static::assertEquals(0, $calculatedLineItem->getPrice()->getTotalPrice());

        $calculatedTaxes = $calculatedLineItem->getPrice()->getCalculatedTaxes();
        static::assertNotNull($calculatedTaxes);
        static::assertEquals(0, $calculatedTaxes->getAmount());
    }

    public function testOrderCartSendMail(): void
    {
        if (!$this->getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $context = $this->getSalesChannelContext();

        $contextService = $this->getContainer()->get(SalesChannelContextService::class);

        $addressId = Uuid::randomHex();

        $mail = 'test@shopware.com';
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context->getContext());

        $newtoken = $this->accountService->login($mail, $context);

        $context = $contextService->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $newtoken));

        $lineItem = (new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $this->productId, 'referencedId' => $this->productId], $context);

        $cartService = $this->getContainer()->get(CartService::class);

        $cart = $cartService->getCart($context->getToken(), $context);

        $cart = $cartService->add($cart, $lineItem, $context);

        $this->setDomainForSalesChannel('http://shopware.local', Defaults::LANGUAGE_SYSTEM, $context->getContext());

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $systemConfigService->set('core.basicInformation.email', 'test@example.org');

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $phpunit = $this;
        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $phpunit): void {
            $eventDidRun = true;
            $phpunit->assertStringContainsString('Shipping costs: €0.00', $event->getContents()['text/html']);
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $cartService->order($cart, $context, new RequestDataBag());

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testCartCreatedWithGivenToken(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $token = Uuid::randomHex();
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($token, $context);

        static::assertSame($token, $cart->getToken());
    }

    private function createCustomer(string $addressId, string $mail, string $password, Context $context): void
    {
        $this->connection->executeStatement('DELETE FROM customer WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $this->customerRepository->create([
            [
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'street' => 'test',
                    'city' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $mail,
                'password' => $password,
                'lastName' => 'not',
                'firstName' => 'match',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => 'not',
            ],
        ], $context);
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $this->addCountriesToSalesChannel();

        return $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    private function setDomainForSalesChannel(string $domain, string $languageId, Context $context): void
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        try {
            $data = [
                'id' => TestDefaults::SALES_CHANNEL,
                'domains' => [
                    [
                        'languageId' => $languageId,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => $domain,
                    ],
                ],
            ];

            $salesChannelRepository->update([$data], $context);
        } catch (\Exception) {
            //ignore if domain already exists
        }
    }
}
