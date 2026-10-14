/**
 * @sw-package fundamentals@after-sales
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { ruleConditionTypeDataProvider } from './condition-type-data-provider.decorator';
import RuleConditionService from '../service/rule-condition.service';

const assignments = [
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

describe('rule condition assignment restrictions', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it.each(assignments)('allows Administration source conditions and blocks flow conditions for %s', async (assignment) => {
        const service = ruleConditionTypeDataProvider(new RuleConditionService());
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
                isRecordSelectableCallback(item: { conditions: EntityCollection<'rule_condition'> }) {
                    return { isSelectable: !service.isRuleRestricted(item.conditions, assignment) };
                },
            },
        });
        await flushPromises();

        expect(wrapper.get('.sw-data-grid__row--0 input[type="checkbox"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('.sw-data-grid__row--1 input[type="checkbox"]').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('.sw-data-grid__row--2 input[type="checkbox"]').attributes('disabled')).toBe('');
        expect(wrapper.get('.sw-data-grid__row--3 input[type="checkbox"]').attributes('disabled')).toBe('');
    });
});
