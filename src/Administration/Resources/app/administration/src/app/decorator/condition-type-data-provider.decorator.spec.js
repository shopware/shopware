/**
 * @sw-package fundamentals@after-sales
 */
import {
    CONDITIONS,
    AWARENESS_CONFIGURATIONS,
    ruleConditionTypeDataProvider,
} from 'src/app/decorator/condition-type-data-provider.decorator';
import RuleConditionService from 'src/app/service/rule-condition.service';

// Guardrail list: Adding or removing a condition is a deliberate change
const EXPECTED_CONDITION_TYPES = [
    'dateRange',
    'timeRange',
    'numberOfReviews',
    'customerOrderCount',
    'customerDaysSinceLastOrder',
    'salesChannel',
    'currency',
    'language',
    'cartTaxDisplay',
    'customerBillingCountry',
    'customerBillingStreet',
    'customerBillingZipCode',
    'customerCustomerGroup',
    'customerRequestedGroup',
    'customerTag',
    'customerCustomerNumber',
    'customerDifferentAddresses',
    'customerEmail',
    'customerLastName',
    'customerIsCompany',
    'customerIsGuest',
    'customerIsNewsletterRecipient',
    'customerShippingCountry',
    'customerShippingStreet',
    'customerShippingZipCode',
    'customerLoggedIn',
    'customerBillingCity',
    'customerBillingState',
    'customerIsActive',
    'customerShippingCity',
    'customerShippingState',
    'customerAge',
    'customerDaysSinceLastLogin',
    'customerDaysSinceFirstLogin',
    'customerAffiliateCode',
    'customerCampaignCode',
    'orderAffiliateCode',
    'orderCampaignCode',
    'cartCartAmount',
    'cartPositionPrice',
    'cartGoodsCount',
    'cartTotalPurchasePrice',
    'cartGoodsPrice',
    'cartLineItemOfType',
    'cartLineItem',
    'cartLineItemsInCartCount',
    'cartLineItemTotalPrice',
    'cartLineItemUnitPrice',
    'cartLineItemWithQuantity',
    'cartHasDeliveryFreeItem',
    'dayOfWeek',
    'cartWeight',
    'cartVolume',
    'cartShippingCost',
    'cartLineItemTag',
    'alwaysValid',
    'cartLineItemProperty',
    'cartLineItemIsNew',
    'cartLineItemOfManufacturer',
    'cartLineItemPurchasePrice',
    'cartLineItemCreationDate',
    'cartLineItemReleaseDate',
    'cartLineItemClearanceSale',
    'cartLineItemPromoted',
    'cartLineItemInCategory',
    'cartLineItemInProductStream',
    'cartLineItemTaxation',
    'cartLineItemDimensionWidth',
    'cartLineItemDimensionHeight',
    'cartLineItemDimensionLength',
    'cartLineItemDimensionWeight',
    'cartLineItemDimensionVolume',
    'cartLineItemListPrice',
    'cartLineItemListPriceRatio',
    'cartLineItemCustomField',
    'cartLineItemActualStock',
    'customerCustomField',
    'paymentMethod',
    'shippingMethod',
    'cartLineItemGoodsTotal',
    'customerOrderTotalAmount',
    'promotionLineItem',
    'promotionCodeOfType',
    'promotionsInCartCount',
    'promotionValue',
    'customerBirthday',
    'customerCreatedByAdmin',
    'customerSalutation',
    'cartLineItemProductStates',
    'cartLineItemProductType',
    'orderTag',
    'orderTrackingCode',
    'orderDeliveryStatus',
    'adminSalesChannelSource',
    'orderTransactionStatus',
    'orderStatus',
    'orderCreatedByAdmin',
    'orderCustomField',
    'orderDocumentType',
    'orderDocumentTypeSent',
    'cartLineItemPropertyValue',
    'cartLineItemVariantValue',
];

// Guardrail list: Adding or removing an awareness config is a deliberate change.
const EXPECTED_AWARENESS_CONFIGURATIONS = [
    'personaPromotions',
    'orderPromotions',
    'cartPromotions',
    'promotionSetGroups',
    'promotionDiscounts',
    'shippingMethodPriceCalculations',
    'shippingMethodPrices',
    'paymentMethods',
    'shippingMethods',
];

describe('app/decorator/condition-type-data-provider.decorator', () => {
    let service;

    beforeAll(async () => {
        Shopware.Service().register('ruleConditionDataProviderService', () => {
            return new RuleConditionService();
        });

        service = Shopware.Service('ruleConditionDataProviderService');
    });

    it('should register exactly the expected rule conditions', () => {
        const registered = Object.keys(service.$store);

        expect(registered).toHaveLength(EXPECTED_CONDITION_TYPES.length);
        expect([...registered].sort()).toEqual([...EXPECTED_CONDITION_TYPES].sort());
    });

    it('should register exactly the expected awareness configurations', () => {
        const ruleAwareness = service.awarenessConfiguration;
        const registered = Object.keys(ruleAwareness);

        expect(registered).toHaveLength(EXPECTED_AWARENESS_CONFIGURATIONS.length);
        expect([...registered].sort()).toEqual([...EXPECTED_AWARENESS_CONFIGURATIONS].sort());
    });

    it('should not declare duplicate rule condition types', () => {
        const types = CONDITIONS.map((condition) => condition.type);

        expect(new Set(types).size).toBe(types.length);
    });

    it('should not declare duplicate awareness configuration names', () => {
        const names = AWARENESS_CONFIGURATIONS(service).map((aware) => aware.name);

        expect(new Set(names).size).toBe(names.length);
    });

    it('every rule condition should declare at least one scope', () => {
        const conditionsWithoutScope = CONDITIONS.filter((condition) => condition.scopes.length === 0);

        expect(conditionsWithoutScope).toHaveLength(0);
    });

    it('every awareness configuration should declare at least one restriction', () => {
        const emptyConfigs = AWARENESS_CONFIGURATIONS(service).filter(
            (aware) => !aware.config.notEquals?.length && !aware.config.equalsAny?.length,
        );

        expect(emptyConfigs).toHaveLength(0);
    });

    it('awareness configurations should only reference registered condition types', () => {
        const knownTypes = new Set(Object.keys(service.$store));

        const referenced = AWARENESS_CONFIGURATIONS(service).flatMap((aware) => [
            ...(aware.config.notEquals ?? []),
            ...(aware.config.equalsAny ?? []),
        ]);

        const unknown = [...new Set(referenced)].filter((type) => !knownTypes.has(type));

        expect(unknown).toHaveLength(0);
    });

    it.each(CONDITIONS.filter((condition) => Boolean(condition.removedInFeature)))(
        'should skip $type while feature flag $removedInFeature is active',
        ({ type, removedInFeature }) => {
            jest.spyOn(Shopware.Feature, 'isActive').mockImplementation((flag) => flag === removedInFeature);

            const conditionService = ruleConditionTypeDataProvider(new RuleConditionService());
            expect(Object.keys(conditionService.$store)).not.toContain(type);

            jest.restoreAllMocks();
        },
    );

    it('should add app script conditions', () => {
        service.addScriptConditions([
            {
                id: 'bar',
                name: 'foo',
                group: 'misc',
                config: {},
            },
        ]);

        const condition = service.getByType('bar');

        expect(condition.component).toBe('sw-condition-script');
        expect(condition.type).toBe('scriptRule');
        expect(condition.label).toBe('foo');
    });
});
