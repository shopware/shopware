/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-settings-rule-selection', { sync: true }), {
        props: {
            discount: { discountRules: [] },
        },
        global: {
            provide: {
                acl: { can: () => true },
                feature: { isActive: (flag) => Shopware.Feature.isActive(flag) },
            },
            stubs: {
                'sw-container': { template: '<div><slot /></div>' },
                'sw-promotion-v2-wizard-description': true,
                'sw-select-rule-create': {
                    props: ['ruleFilter'],
                    template: '<div class="sw-select-rule-create"></div>',
                },
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-rule-selection', () => {
    it('filters the rules with its own criteria before 6.8 and with the default criteria of the rule select after', async () => {
        const wrapper = await createWrapper();

        const ruleFilter = wrapper.getComponent('.sw-select-rule-create').props('ruleFilter');

        // Without a filter, the rule select falls back to its own default criteria.
        const expectedSortings = Shopware.Feature.isActive('V6_8_0_0')
            ? undefined
            : [{ field: 'name', order: 'ASC', naturalSorting: false }];
        expect(ruleFilter?.sortings).toEqual(expectedSortings);
    });
});
