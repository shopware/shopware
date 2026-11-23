/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

async function createWrapper({ discount = { discountRules: [{ id: 'ruleId' }] }, privileges = ['promotion.editor'] } = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-settings-trigger', { sync: true }), {
        props: { discount },
        global: {
            provide: {
                acl: { can: (privilege) => privileges.includes(privilege) },
            },
            stubs: {
                'sw-container': { template: '<div class="sw-container"><slot /></div>' },
                'sw-promotion-v2-wizard-description': {
                    template: '<p class="sw-promotion-v2-wizard-description"><slot /></p>',
                },
                'mt-select': {
                    props: ['modelValue', 'options', 'disabled'],
                    template: '<div class="mt-select"></div>',
                },
                'sw-select-rule-create': {
                    props: { rules: Array, ruleScope: Array, localMode: Boolean, disabled: Boolean, ruleFilter: Object },
                    emits: ['update:rules'],
                    template: '<div class="sw-select-rule-create"></div>',
                },
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-trigger', () => {
    it('describes the trigger', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-promotion-v2-wizard-description').text()).toBe(
            'sw-promotion-v2.detail.discounts.wizard.shipping-discount.description',
        );
    });

    it('shows the trigger settings when the discount has rules', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-promotion-v2-settings-trigger-settings__use-trigger input').element.checked).toBe(true);
        expect(wrapper.find('.sw-select-rule-create').exists()).toBe(true);
    });

    it('shows the trigger settings once the trigger is switched on', async () => {
        const wrapper = await createWrapper({ discount: { discountRules: [] } });

        expect(wrapper.find('.sw-container').exists()).toBe(false);

        await wrapper.get('.sw-promotion-v2-settings-trigger-settings__use-trigger input').setChecked();

        expect(wrapper.find('.sw-select-rule-create').exists()).toBe(true);
    });

    it('offers the single trigger type preselected', async () => {
        const wrapper = await createWrapper();

        const triggerType = wrapper.getComponent('.mt-select');

        expect(triggerType.props('modelValue')).toBe('single');
        expect(triggerType.props('options')).toEqual([
            {
                id: 'single',
                value: 'single',
                label: 'sw-promotion-v2.detail.discounts.settings.trigger.triggerType.displaySingleTrigger',
            },
            {
                id: 'multi',
                value: 'multi',
                label: 'sw-promotion-v2.detail.discounts.settings.trigger.triggerType.displayMultiTrigger',
            },
        ]);
    });

    it('selects cart rules of the discount locally', async () => {
        const discountRules = [{ id: 'ruleId' }];
        const wrapper = await createWrapper({ discount: { discountRules } });

        const ruleSelect = wrapper.getComponent('.sw-select-rule-create');

        expect(ruleSelect.props('rules')).toEqual(discountRules);
        expect(ruleSelect.props('ruleScope')).toEqual(['cart']);
        expect(ruleSelect.props('localMode')).toBe(true);
        expect(ruleSelect.props('disabled')).toBe(false);
    });

    it('considers advanced rules as long as the discount has rules', async () => {
        const discount = { discountRules: [{ id: 'ruleId' }], considerAdvancedRules: false };
        const wrapper = await createWrapper({ discount });
        const ruleSelect = wrapper.getComponent('.sw-select-rule-create');

        await ruleSelect.vm.$emit('update:rules', [{ id: 'ruleId' }, { id: 'otherRuleId' }]);
        expect(discount.considerAdvancedRules).toBe(true);

        await ruleSelect.vm.$emit('update:rules', []);
        expect(discount.considerAdvancedRules).toBe(false);
    });

    it('disables the trigger settings without the promotion editor privilege', async () => {
        const wrapper = await createWrapper({ privileges: [] });

        expect(wrapper.getComponent('.mt-select').props('disabled')).toBe(true);
        expect(wrapper.getComponent('.sw-select-rule-create').props('disabled')).toBe(true);
    });

    describe('rule filter of the rule select', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-promotion-v2-settings-trigger');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleCriteria computed.
        it.deprecated('v6.8.0.0')('passes ruleCriteria to the rule select without reporting its deprecation', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter').sortings).toEqual([
                expect.objectContaining({ field: 'name', order: 'ASC' }),
            ]);
            expect(deprecations).not.toHaveTriggered('sw-promotion-v2-settings-trigger.ruleCriteria is deprecated');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleCriteria computed.
        it.deprecated('v6.8.0.0')('passes an override of ruleCriteria to the rule select', async () => {
            const overriddenCriteria = new Shopware.Data.Criteria(1, 5);
            Shopware.Component.override('sw-promotion-v2-settings-trigger', {
                computed: {
                    ruleCriteria() {
                        return overriddenCriteria;
                    },
                },
            });

            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter')).toBe(overriddenCriteria);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('leaves the rule filter to the default of the rule select', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter')).toBeUndefined();
        });
    });
});
