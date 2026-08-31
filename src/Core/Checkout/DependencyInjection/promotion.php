<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionCartRule\PromotionCartRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountRule\PromotionDiscountRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderRule\PromotionOrderRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaCustomer\PromotionPersonaCustomerDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionPersonaRule\PromotionPersonaRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroupRule\PromotionSetGroupRuleDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Checkout\Promotion\Api\PromotionActionController;
use Shopware\Core\Checkout\Promotion\Api\PromotionController;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsSubscriber;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackageRules;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterServiceRegistry;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Picker\HorizontalPicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Picker\VerticalPicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceAsc;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceDesc;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryProcessor;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Cart\PromotionRedemptionLocker;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionExclusionUpdater;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Shopware\Core\Checkout\Promotion\DataAbstractionLayer\PromotionValidator as PromotionDalValidator;
use Shopware\Core\Checkout\Promotion\Gateway\PromotionGateway;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Checkout\Promotion\Service\PromotionDateTimeService;
use Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Checkout\Promotion\Validator\PromotionValidator;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // DAL DEFINITIONS + SERVICES
    $services->set(PromotionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionIndividualCodeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionDiscountDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionDiscountRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionSetGroupDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionSetGroupRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionOrderRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionPersonaCustomerDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionPersonaRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionCartRuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionDiscountPriceDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(PromotionValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionRedemptionLocker::class)
        ->args([
            service(LockManager::class),
        ])
        ->tag('kernel.event_subscriber');

    // CART CALCULATORS
    $services->set(PromotionItemBuilder::class);

    $services->set(PromotionCollector::class)
        ->args([
            service(PromotionGateway::class),
            service(PromotionItemBuilder::class),
            service(HtmlSanitizer::class),
            service(Connection::class),
        ])
        ->tag('shopware.cart.collector', ['priority' => 4900]);

    $services->set(PromotionProcessor::class)
        ->args([
            service(PromotionCalculator::class),
            service(LineItemGroupBuilder::class),
        ])
        ->tag('shopware.cart.processor', ['priority' => 4900]);

    $services->set(PromotionDeliveryProcessor::class)
        ->args([
            service(PromotionDeliveryCalculator::class),
            service(LineItemGroupBuilder::class),
        ])
        ->tag('shopware.cart.processor', ['priority' => -5100]);

    $services->set(PromotionCalculator::class)
        ->args([
            service(AmountCalculator::class),
            service(AbsolutePriceCalculator::class),
            service(LineItemGroupBuilder::class),
            service(DiscountCompositionBuilder::class),
            service(AdvancedPackageFilter::class),
            service(AdvancedPackagePicker::class),
            service(AdvancedPackageRules::class),
            service(LineItemQuantitySplitter::class),
            service(PercentagePriceCalculator::class),
            service(CartScopeDiscountPackager::class),
            service(SetGroupScopeDiscountPackager::class),
            service(SetScopeDiscountPackager::class),
        ]);

    $services->set(PromotionDeliveryCalculator::class)
        ->args([
            service(QuantityPriceCalculator::class),
            service(PercentagePriceCalculator::class),
            service(PromotionItemBuilder::class),
        ]);

    // SUBSCRIBERS
    $services->set(StorefrontCartSubscriber::class)
        ->args([
            service('event_dispatcher'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    // API CONTROLLERS
    $services->set(PromotionActionController::class)
        ->public()
        ->args([
            service(LineItemGroupServiceRegistry::class),
            service(FilterServiceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(PromotionController::class)
        ->public()
        ->args([
            service(PromotionCodeService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    // FILTER SERVICES
    $services->set(AdvancedPackageFilter::class)
        ->args([
            service(FilterServiceRegistry::class),
        ]);

    $services->set(AdvancedPackagePicker::class)
        ->args([
            service(FilterServiceRegistry::class),
        ]);

    $services->set(AdvancedPackageRules::class);

    $services->set(FilterServiceRegistry::class)
        ->args([
            tagged_iterator('promotion.filter.sorter'),
            tagged_iterator('promotion.filter.picker'),
        ]);

    $services->set(FilterSorterPriceAsc::class)
        ->tag('promotion.filter.sorter');

    $services->set(FilterSorterPriceDesc::class)
        ->tag('promotion.filter.sorter');

    $services->set(VerticalPicker::class)
        ->tag('promotion.filter.picker');

    $services->set(HorizontalPicker::class)
        ->tag('promotion.filter.picker');

    // ADDITIONAL SERVICES
    $services->set(PromotionGateway::class)
        ->args([
            service('promotion.repository'),
            service(PromotionDateTimeService::class),
        ]);

    $services->set(PromotionIndividualCodeRedeemer::class)
        ->args([
            service('promotion_individual_code.repository'),
            service('order_customer.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionDateTimeService::class);

    $services->set(PromotionCodeService::class)
        ->args([
            service('promotion.repository'),
            service('promotion_individual_code.repository'),
            service(Connection::class),
        ]);

    $services->set(DiscountCompositionBuilder::class);

    $services->set(PromotionIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('promotion.repository'),
            service(PromotionExclusionUpdater::class),
            service(PromotionRedemptionUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(PromotionRedemptionUpdater::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PromotionExclusionUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PromotionDalValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CartScopeDiscountPackager::class);

    $services->set(SetGroupScopeDiscountPackager::class)
        ->args([
            service(LineItemGroupBuilder::class),
        ]);

    $services->set(SetScopeDiscountPackager::class)
        ->args([
            service(LineItemGroupBuilder::class),
        ]);

    $services->set(CartPromotionsSubscriber::class)
        ->tag('kernel.event_subscriber');
};
