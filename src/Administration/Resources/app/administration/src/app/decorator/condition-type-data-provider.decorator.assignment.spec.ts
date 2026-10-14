/**
 * @sw-package fundamentals@after-sales
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
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

        wrapper = shallowMount(await wrapTestComponent('sw-advanced-selection-rule', { sync: true }), {
            props: {
                ruleAwareGroupKey: assignment,
            },
            global: {
                provide: {
                    ruleConditionDataProviderService: service,
                },
                stubs: {
                    'sw-entity-advanced-selection-modal': {
                        data() {
                            return { rules };
                        },
                        template: `
                            <div>
                                <div v-for="rule in rules" :key="rule.id" :data-rule-id="rule.id">
                                    <slot name="column-name" :item="rule" />
                                </div>
                            </div>
                        `,
                    },
                },
            },
        });

        expect(wrapper.get('[data-rule-id="admin-only"] span').classes()).not.toContain(
            'sw-advanced-selection-rule-disabled',
        );
        expect(wrapper.get('[data-rule-id="storefront-only"] span').classes()).not.toContain(
            'sw-advanced-selection-rule-disabled',
        );
        expect(wrapper.get('[data-rule-id="flow-only"] span').classes()).toContain('sw-advanced-selection-rule-disabled');
        expect(wrapper.get('[data-rule-id="mixed"] span').classes()).toContain('sw-advanced-selection-rule-disabled');
    });
});
