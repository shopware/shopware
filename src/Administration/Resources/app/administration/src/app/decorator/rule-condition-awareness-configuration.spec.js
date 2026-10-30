/**
 * @sw-package fundamentals@after-sales
 */
import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { AWARENESS_CONFIGURATIONS, ruleConditionTypeDataProvider } from './condition-type-data-provider.decorator';
import RuleConditionService from '../service/rule-condition.service';

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
    let wrapper;
    let service;

    beforeEach(() => {
        service = ruleConditionTypeDataProvider(new RuleConditionService());
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should register exactly the expected awareness configurations', () => {
        const ruleAwareness = service.awarenessConfiguration;
        const registered = Object.keys(ruleAwareness);

        expect(registered).toHaveLength(EXPECTED_AWARENESS_CONFIGURATIONS.length);
        expect([...registered].sort()).toEqual([...EXPECTED_AWARENESS_CONFIGURATIONS].sort());
    });

    it('should not declare duplicate awareness configuration names', () => {
        const names = AWARENESS_CONFIGURATIONS(service).map((aware) => aware.name);

        expect(new Set(names).size).toBe(names.length);
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

    it.each(EXPECTED_AWARENESS_CONFIGURATIONS)(
        'allows Administration source conditions and blocks flow conditions for %s',
        async (assignment) => {
            const rules = [
                {
                    id: 'admin-only',
                    name: 'Administration only',
                    conditions: [
                        { type: 'alwaysValid' },
                        { type: 'adminSalesChannelSource', value: { hasAdminSalesChannelSource: true } },
                    ],
                },
                {
                    id: 'storefront-only',
                    name: 'Storefront only',
                    conditions: [
                        { type: 'alwaysValid' },
                        { type: 'adminSalesChannelSource', value: { hasAdminSalesChannelSource: false } },
                    ],
                },
                {
                    id: 'flow-only',
                    name: 'Order status',
                    conditions: [{ type: 'alwaysValid' }, { type: 'orderStatus' }],
                },
                {
                    id: 'mixed',
                    name: 'Administration source and order status',
                    conditions: [
                        { type: 'alwaysValid' },
                        { type: 'adminSalesChannelSource', value: { hasAdminSalesChannelSource: true } },
                        { type: 'orderStatus' },
                    ],
                },
            ];

            const repository = Shopware.Service('repositoryFactory').create('rule');
            const items = new EntityCollection(
                '/rule',
                'rule',
                Shopware.Context.api,
                null,
                rules.map((rule) => Object.assign(repository.create(Shopware.Context.api), rule)),
                rules.length,
            );

            wrapper = shallowMount(await wrapTestComponent('sw-entity-advanced-selection-modal-grid', { sync: true }), {
                props: {
                    dataSource: items,
                    repository,
                    columns: [{ property: 'name', label: 'Name' }],
                    isRecordSelectableCallback(item) {
                        return { isSelectable: !service.isRuleRestricted(item.conditions, assignment) };
                    },
                },
            });
            await flushPromises();

            expect(wrapper.get('.sw-data-grid__row--0 input[type="checkbox"]').attributes('disabled')).toBeUndefined();
            expect(wrapper.get('.sw-data-grid__row--1 input[type="checkbox"]').attributes('disabled')).toBeUndefined();
            expect(wrapper.get('.sw-data-grid__row--2 input[type="checkbox"]').attributes('disabled')).toBe('');
            expect(wrapper.get('.sw-data-grid__row--3 input[type="checkbox"]').attributes('disabled')).toBe('');
        },
    );
});
