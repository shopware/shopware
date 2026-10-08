<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupCollection;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\TaxProvider\TaxProviderCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleEntity::class)]
class RuleEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $productPrices = new ProductPriceCollection();
        $shippingMethods = new ShippingMethodCollection();
        $paymentMethods = new PaymentMethodCollection();
        $conditions = new RuleConditionCollection();
        $shippingMethodPrices = new ShippingMethodPriceCollection();
        $shippingMethodPriceCalculations = new ShippingMethodPriceCollection();
        $promotionDiscounts = new PromotionDiscountCollection();
        $promotionSetGroups = new PromotionSetGroupCollection();
        $personaPromotions = new PromotionCollection();
        $orderPromotions = new PromotionCollection();
        $cartPromotions = new PromotionCollection();
        $flowSequences = new FlowSequenceCollection();
        $tags = new TagCollection();
        $taxProviders = new TaxProviderCollection();

        $rule = new RuleEntity();
        $rule->setName('Cart above 100');
        $rule->setDescription('Applies to big carts');
        $rule->setPriority(5);
        $rule->setInvalid(false);
        $rule->setAreas(['payment']);
        $rule->setModuleTypes(['price']);
        $rule->setProductPrices($productPrices);
        $rule->setShippingMethods($shippingMethods);
        $rule->setPaymentMethods($paymentMethods);
        $rule->setConditions($conditions);
        $rule->setShippingMethodPrices($shippingMethodPrices);
        $rule->setShippingMethodPriceCalculations($shippingMethodPriceCalculations);
        $rule->setPromotionDiscounts($promotionDiscounts);
        $rule->setPromotionSetGroups($promotionSetGroups);
        $rule->setPersonaPromotions($personaPromotions);
        $rule->setOrderPromotions($orderPromotions);
        $rule->setCartPromotions($cartPromotions);
        $rule->setFlowSequences($flowSequences);
        $rule->setTags($tags);
        $rule->setTaxProviders($taxProviders);

        static::assertSame('Cart above 100', $rule->getName());
        static::assertSame('Applies to big carts', $rule->getDescription());
        static::assertSame(5, $rule->getPriority());
        static::assertFalse($rule->isInvalid());
        static::assertSame(['payment'], $rule->getAreas());
        static::assertSame(['price'], $rule->getModuleTypes());
        static::assertSame($productPrices, $rule->getProductPrices());
        static::assertSame($shippingMethods, $rule->getShippingMethods());
        static::assertSame($paymentMethods, $rule->getPaymentMethods());
        static::assertSame($conditions, $rule->getConditions());
        static::assertSame($shippingMethodPrices, $rule->getShippingMethodPrices());
        static::assertSame($shippingMethodPriceCalculations, $rule->getShippingMethodPriceCalculations());
        static::assertSame($promotionDiscounts, $rule->getPromotionDiscounts());
        static::assertSame($promotionSetGroups, $rule->getPromotionSetGroups());
        static::assertSame($personaPromotions, $rule->getPersonaPromotions());
        static::assertSame($orderPromotions, $rule->getOrderPromotions());
        static::assertSame($cartPromotions, $rule->getCartPromotions());
        static::assertSame($flowSequences, $rule->getFlowSequences());
        static::assertSame($tags, $rule->getTags());
        static::assertSame($taxProviders, $rule->getTaxProviders());
    }

    public function testPayloadIsReadableOutsideTwig(): void
    {
        $payload = new AndRule();
        $rule = $this->ruleWithInternalPayload();
        $rule->setPayload($payload);

        static::assertSame($payload, $rule->getPayload());
    }

    public function testPayloadIsGuardedInsideTwig(): void
    {
        $rule = $this->ruleWithInternalPayload();
        $rule->setPayload(new AndRule());

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('payload', RuleEntity::class));
        $rule->getPayload();
    }

    private function ruleWithInternalPayload(): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->internalSetEntityData(RuleDefinition::ENTITY_NAME, new FieldVisibility(['payload']));

        return $rule;
    }
}
