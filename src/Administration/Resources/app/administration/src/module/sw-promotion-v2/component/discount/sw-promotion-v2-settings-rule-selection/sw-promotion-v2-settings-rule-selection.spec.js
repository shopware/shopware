/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

async function createWrapper({ discount = { discountRules: [{ id: 'ruleId' }] }, privileges = ['promotion.editor'] } = {}) {
    return mount(await wrapTestComponent('sw-promotion-v2-settings-rule-selection', { sync: true }), {
        props: { discount },
        global: {
            provide: {
                acl: { can: (privilege) => privileges.includes(privilege) },
            },
            stubs: {
                'sw-promotion-v2-wizard-description': {
                    template: '<p class="sw-promotion-v2-wizard-description"><slot /></p>',
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

describe('src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-rule-selection', () => {
    it('describes the rule selection', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-promotion-v2-wizard-description').text()).toBe(
            'sw-promotion-v2.detail.discounts.settings.ruleSelection.description',
        );
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

    it('writes the selected rules to the discount', async () => {
        const discount = { discountRules: [] };
        const wrapper = await createWrapper({ discount });
        const selectedRules = [{ id: 'otherRuleId' }];

        await wrapper.getComponent('.sw-select-rule-create').vm.$emit('update:rules', selectedRules);

        expect(discount.discountRules).toBe(selectedRules);
    });

    it('disables the rule selection without the promotion editor privilege', async () => {
        const wrapper = await createWrapper({ privileges: [] });

        expect(wrapper.getComponent('.sw-select-rule-create').props('disabled')).toBe(true);
    });

    describe('rule filter of the rule select', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-promotion-v2-settings-rule-selection');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleCriteria computed.
        it.deprecated('v6.8.0.0')('passes ruleCriteria to the rule select without reporting its deprecation', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter').sortings).toEqual([
                expect.objectContaining({ field: 'name', order: 'ASC' }),
            ]);
            expect(deprecations).not.toHaveTriggered('sw-promotion-v2-settings-rule-selection.ruleCriteria is deprecated');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleCriteria computed.
        it.deprecated('v6.8.0.0')('passes an override of ruleCriteria to the rule select', async () => {
            const overriddenCriteria = new Shopware.Data.Criteria(1, 5);
            Shopware.Component.override('sw-promotion-v2-settings-rule-selection', {
                computed: {
                    ruleCriteria() {
                        return overriddenCriteria;
                    },
                },
            });

            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter')).toBe(overriddenCriteria);
            expect(deprecations).toHaveTriggered(
                'Component.override(\'sw-promotion-v2-settings-rule-selection\') overrides the deprecated computed "ruleCriteria", so the override stops working once "ruleCriteria" is removed with V6_8_0_0.',
            );
        });

        it.activeFeatureFlags(['v6.8.0.0'])('leaves the rule filter to the default of the rule select', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-select-rule-create').props('ruleFilter')).toBeUndefined();
        });
    });
});
