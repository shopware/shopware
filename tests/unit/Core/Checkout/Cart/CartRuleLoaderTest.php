<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartFactory;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Extension\CheckoutCartRuleLoaderExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Rule\CartTaxDisplayRule;
use Shopware\Core\Checkout\Cart\RuleLoader;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\LanguageInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartRuleLoader::class)]
class CartRuleLoaderTest extends TestCase
{
    public function testLoadByTokenCreatesNewCart(): void
    {
        $newCart = new Cart('test');
        $factory = $this->createMock(CartFactory::class);
        $factory
            ->expects($this->once())
            ->method('createNew')
            ->with('test')
            ->willReturn($newCart);

        $persister = $this->createMock(AbstractCartPersister::class);
        $persister
            ->expects($this->once())
            ->method('load')
            ->with('test')
            ->willThrowException(CartException::tokenNotFound('test'));

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getToken')
            ->willReturn('test');
        $salesChannelContext
            ->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $calculatedCart = new Cart('calculated');
        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly(3))
            ->method('process')
            ->with(static::isInstanceOf(Cart::class), $salesChannelContext, static::isInstanceOf(CartBehavior::class))
            ->willReturn($calculatedCart);

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('hasListeners')->willReturn(true);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(CheckoutCartRuleLoaderExtension::class));

        $cartRuleLoader = new CartRuleLoader(
            $persister,
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            $factory,
            new ExtensionDispatcher($dispatcher),
            static::createStub(AbstractTranslator::class),
        );

        static::assertSame($calculatedCart, $cartRuleLoader->loadByToken($salesChannelContext, $salesChannelContext->getToken())->getCart());
    }

    public function testProcessorHasCorrectRuleIds(): void
    {
        $country = new CountryEntity();
        $country->setId(Generator::COUNTRY);
        $country->setCustomerTax(new TaxFreeConfig());

        $customer = new CustomerEntity();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $customer->setId('test-id');

        $salesChannelContext = Generator::generateSalesChannelContext(customer: $customer, country: $country);

        $rule1 = new RuleEntity();
        $rule1->setId(Uuid::randomHex());
        $rule1->setName($rule1->getId());
        $rule1->setPriority(1);
        $rule1->setAreas([RuleAreas::PRODUCT_AREA, RuleAreas::PAYMENT_AREA]);
        $rule1->setPayload(new AlwaysValidRule());

        $rule2 = new RuleEntity();
        $rule2->setId(Uuid::randomHex());
        $rule2->setName($rule2->getId());
        $rule2->setPriority(2);
        $rule2->setAreas([RuleAreas::PRODUCT_AREA]);
        $rule2->setPayload(new AlwaysValidRule());

        $rule3 = new RuleEntity();
        $rule3->setId(Uuid::randomHex());
        $rule3->setName($rule3->getId());
        $rule3->setPriority(3);
        $rule3->setAreas([RuleAreas::PAYMENT_AREA, RuleAreas::PAYMENT_AREA]);
        $rule3->setPayload(new AlwaysValidRule());

        $ruleIds = [$rule1->getId(), $rule2->getId(), $rule3->getId()];
        $areaRuleIds = [
            RuleAreas::PRODUCT_AREA => [$rule1->getId(), $rule2->getId()],
            RuleAreas::PAYMENT_AREA => [$rule1->getId(), $rule3->getId()],
        ];

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection([$rule1, $rule2, $rule3]))
        ;

        $processor = $this->createMock(Processor::class);
        $processor
            ->expects($this->exactly(3))
            ->method('process')
            ->with(static::isInstanceOf(Cart::class), static::callback(static function (SalesChannelContext $context) use ($ruleIds, $areaRuleIds) {
                static::assertSame($ruleIds, $context->getRuleIds());
                static::assertSame($areaRuleIds, $context->getAreaRuleIds());

                return true;
            }), static::isInstanceOf(CartBehavior::class))
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('hasListeners')->willReturn(true);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(CheckoutCartRuleLoaderExtension::class));

        $cartRuleLoader = new CartRuleLoader(
            static::createStub(AbstractCartPersister::class),
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            static::createStub(CartFactory::class),
            new ExtensionDispatcher($dispatcher),
            static::createStub(AbstractTranslator::class),
        );

        $cart = new Cart('test');
        $cart->setRuleIds($ruleIds);
        $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());
    }

    public function testTranslatesProcessedCartErrorsWithSalesChannelLocale(): void
    {
        $reason = 'rule not matching or inactive';
        $customer = new CustomerEntity();
        $customer->setId('test-id');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        $country = new CountryEntity();
        $country->setId(Generator::COUNTRY);
        $country->setCustomerTax(new TaxFreeConfig());

        $salesChannelContext = Generator::generateSalesChannelContext(
            customer: $customer,
            languageInfo: new LanguageInfo('German', 'de-DE'),
            country: $country,
        );

        $cart = new Cart('test');
        $processedCart = new Cart('processed');
        $processedCart->getErrors()->add(new ShippingMethodBlockedError(
            id: 'shipping-method-id',
            name: 'Standard',
            reason: $reason,
        ));

        $processor = static::createStub(Processor::class);
        $processor
            ->method('process')
            ->willReturn($processedCart);

        $ruleLoader = $this->createMock(RuleLoader::class);
        $ruleLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelContext->getContext())
            ->willReturn(new RuleCollection());

        $translator = $this->createMock(AbstractTranslator::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters, ?string $domain, ?string $locale) use ($salesChannelContext, $reason): string {
                static::assertNull($domain);
                static::assertSame($salesChannelContext->getLanguageInfo()->localeCode, $locale);
                static::assertSame('checkout.shipping-method-blocked', $id);
                static::assertSame([
                    '%id%' => 'shipping-method-id',
                    '%name%' => 'Standard',
                    '%reason%' => $reason,
                ], $parameters);

                return 'Die Versandart "Standard" ist für Ihren aktuellen Warenkorb gesperrt.';
            });

        $cartRuleLoader = new CartRuleLoader(
            static::createStub(AbstractCartPersister::class),
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            static::createStub(CartFactory::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
            $translator,
        );

        $result = $cartRuleLoader->loadByCart($salesChannelContext, $cart, new CartBehavior());

        static::assertSame(
            'Die Versandart "Standard" ist für Ihren aktuellen Warenkorb gesperrt.',
            $result->getCart()->getErrors()->first()?->getTranslatedMessage(),
        );
    }

    #[DataProvider('digitalOnlyCartProvider')]
    public function testStoredDigitalOnlyCartIsCalculatedWithTheBillingTaxesFromTheFirstCalculation(LineItem $lineItem): void
    {
        $context = $this->createContextWithBillingTaxes();
        $shippingTaxRules = $context->getTaxRules();
        $cart = new Cart('test');
        $cart->add($lineItem);

        $calculatedWith = $this->loadAndGetTaxRules($context, $cart);

        static::assertNotContains($shippingTaxRules, $calculatedWith, 'a changed data context hash would load the products again');
        static::assertSame($shippingTaxRules, $context->getTaxRules(), 'product prices outside the cart keep the shipping taxes');
        static::assertSame(CartPrice::TAX_STATE_FREE, $context->getTaxState());
    }

    public function testNewDigitalProductIsCalculatedWithTheBillingTaxesOnceItsTypeIsKnown(): void
    {
        $context = $this->createContextWithBillingTaxes();
        $shippingTaxRules = $context->getTaxRules();
        $cart = new Cart('test');
        $cart->add(new LineItem('e-book', LineItem::PRODUCT_LINE_ITEM_TYPE));

        $calculatedWith = $this->loadAndGetTaxRules($context, $cart, ProductDefinition::TYPE_DIGITAL);

        static::assertSame($shippingTaxRules, $calculatedWith[0], 'the first calculation sets the product type');
        static::assertSame($context->getBillingTaxRules(), array_last($calculatedWith));
        static::assertSame($shippingTaxRules, $context->getTaxRules());
    }

    public function testStoredProductThatIsNoLongerDigitalIsCalculatedWithTheShippingTaxes(): void
    {
        $context = $this->createContextWithBillingTaxes();
        $shippingTaxRules = $context->getTaxRules();
        $cart = new Cart('test');
        $cart->add(self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL));

        $calculatedWith = $this->loadAndGetTaxRules($context, $cart, ProductDefinition::TYPE_PHYSICAL);

        static::assertSame($context->getBillingTaxRules(), $calculatedWith[0]);
        static::assertSame($shippingTaxRules, array_last($calculatedWith));
        static::assertSame($shippingTaxRules, $context->getTaxRules());
        static::assertSame(CartPrice::TAX_STATE_FREE, $context->getTaxState(), 'the shipping country decides the tax state');
    }

    #[DataProvider('storedAndNewDigitalProductProvider')]
    public function testRulesMatchTheTaxStateOfTheBillingCountry(LineItem $product, ?string $loadedProductType): void
    {
        $context = $this->createContextWithBillingTaxes();
        $netRule = (new RuleEntity())->assign([
            'id' => Uuid::randomHex(),
            'name' => 'Tax display net',
            'priority' => 1,
            'payload' => new CartTaxDisplayRule(CartPrice::TAX_STATE_NET),
        ]);
        $cart = new Cart('test');
        $cart->add($product);

        $this->loadAndGetTaxRules($context, $cart, $loadedProductType, new RuleCollection([$netRule]));

        static::assertSame([$netRule->getId()], $context->getRuleIds(), 'the billing country makes the cart net, the shipping country tax-free');
    }

    /**
     * @return iterable<string, array{LineItem, ?string}>
     */
    public static function storedAndNewDigitalProductProvider(): iterable
    {
        yield 'a stored digital product switches before the first calculation' => [self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL), null];
        yield 'a new digital product switches once its type is known' => [new LineItem('e-book', LineItem::PRODUCT_LINE_ITEM_TYPE), ProductDefinition::TYPE_DIGITAL];
    }

    public function testFailedCalculationRestoresTheShippingTaxes(): void
    {
        $context = $this->createContextWithBillingTaxes();
        $shippingTaxRules = $context->getTaxRules();
        $cart = new Cart('test');
        $cart->add(self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL));

        $exception = new \RuntimeException('calculation failed');
        $processor = static::createStub(Processor::class);
        $processor->method('process')->willThrowException($exception);

        try {
            $this->createCartRuleLoader($processor)->loadByCart($context, $cart, new CartBehavior());
            static::fail('the calculation should fail');
        } catch (\RuntimeException $e) {
            static::assertSame($exception, $e);
        }

        static::assertSame($shippingTaxRules, $context->getTaxRules());
        static::assertSame(CartPrice::TAX_STATE_FREE, $context->getTaxState());
    }

    /**
     * @return iterable<string, array{LineItem}>
     */
    public static function digitalOnlyCartProvider(): iterable
    {
        yield 'a digital product' => [self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL)];
        yield 'a digital product nested in a container' => [
            (new LineItem('container', LineItem::CONTAINER_LINE_ITEM))->setChildren(new LineItemCollection([
                self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL),
            ])),
        ];
    }

    #[DataProvider('cartThatIsNotDigitalOnlyProvider')]
    public function testCartThatIsNotDigitalOnlyKeepsTheShippingTaxes(LineItem $lineItem): void
    {
        $context = $this->createContextWithBillingTaxes();
        $cart = new Cart('test');
        $cart->add($lineItem);

        static::assertNotContains($context->getBillingTaxRules(), $this->loadAndGetTaxRules($context, $cart));
        static::assertSame(CartPrice::TAX_STATE_FREE, $context->getTaxState(), 'the shipping country decides the tax state, the billing country would make it net');
    }

    /**
     * @return iterable<string, array{LineItem}>
     */
    public static function cartThatIsNotDigitalOnlyProvider(): iterable
    {
        yield '17: a cart without a product, even with a digital custom line item' => [
            (new LineItem('custom', LineItem::CUSTOM_LINE_ITEM_TYPE))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL),
        ];
        yield '18: a product without a product type counts as physical' => [new LineItem('product', LineItem::PRODUCT_LINE_ITEM_TYPE)];
        yield 'a product type of an extension counts as physical' => [self::createProduct('product', 'subscription')];
        yield 'a nested physical product' => [
            (new LineItem('container', LineItem::CONTAINER_LINE_ITEM))->setChildren(new LineItemCollection([
                self::createProduct('e-book', ProductDefinition::TYPE_DIGITAL),
                self::createProduct('widget', ProductDefinition::TYPE_PHYSICAL),
            ])),
        ];
    }

    private static function createProduct(string $id, string $productType): LineItem
    {
        return (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, $productType);
    }

    private function createContextWithBillingTaxes(): SalesChannelContext
    {
        $billingCountry = new CountryEntity();
        $billingCountry->setId(Uuid::randomHex());
        $billingCountry->setCustomerTax(new TaxFreeConfig());
        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setCountry($billingCountry);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $customer->setActiveBillingAddress($billingAddress);

        $shippingCountry = new CountryEntity();
        $shippingCountry->setId(Generator::COUNTRY);
        $shippingCountry->setCustomerTax(new TaxFreeConfig(enabled: true));

        $context = Generator::generateSalesChannelContext(customer: $customer, country: $shippingCountry);
        // without a currency threshold the country's Tax-free (B2C) setting applies
        $context->getCurrency()->setTaxFreeFrom(0.0);
        $context->setBillingTaxRules(new TaxCollection([(new TaxEntity())->assign(['id' => Generator::TAX, 'taxRate' => 20.0])]));
        $context->setTaxState(CartPrice::TAX_STATE_FREE);

        return $context;
    }

    /**
     * @return list<TaxCollection> the tax rules of each calculation
     */
    private function loadAndGetTaxRules(SalesChannelContext $context, Cart $cart, ?string $loadedProductType = null, RuleCollection $rules = new RuleCollection()): array
    {
        $taxRules = [];
        $processor = static::createStub(Processor::class);
        $processor->method('process')->willReturnCallback(static function (Cart $cart, SalesChannelContext $context) use (&$taxRules, $loadedProductType): Cart {
            $taxRules[] = $context->getTaxRules();

            // like the product processor, each calculation sets the type of the loaded products
            if ($loadedProductType !== null) {
                foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $product) {
                    $product->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, $loadedProductType);
                }
            }

            return $cart;
        });

        $this->createCartRuleLoader($processor, $rules)->loadByCart($context, $cart, new CartBehavior());

        return $taxRules;
    }

    private function createCartRuleLoader(Processor $processor, RuleCollection $rules = new RuleCollection()): CartRuleLoader
    {
        $ruleLoader = static::createStub(RuleLoader::class);
        $ruleLoader->method('load')->willReturn($rules);

        return new CartRuleLoader(
            static::createStub(AbstractCartPersister::class),
            $processor,
            new NullLogger(),
            static::createStub(CacheInterface::class),
            $ruleLoader,
            static::createStub(TaxDetector::class),
            static::createStub(Connection::class),
            static::createStub(CartFactory::class),
            new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
            static::createStub(AbstractTranslator::class),
        );
    }
}
