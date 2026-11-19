/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-settings-trigger', { sync: true }), {
        props: {
            discount: { discountRules: [{ id: 'ruleId' }] },
        },
        global: {
            provide: {
                acl: { can: () => true },
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

describe('src/module/sw-promotion-v2/component/discount/sw-promotion-v2-settings-trigger', () => {
    it('filters the rules with its own criteria', async () => {
        const wrapper = await createWrapper();

        const ruleFilter = wrapper.getComponent('.sw-select-rule-create').props('ruleFilter');

        expect(ruleFilter.sortings).toEqual([{ field: 'name', order: 'ASC', naturalSorting: false }]);
    });
});
