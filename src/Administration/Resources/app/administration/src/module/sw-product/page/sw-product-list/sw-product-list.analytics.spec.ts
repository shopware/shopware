/**
 * @sw-package inventory
 */
import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    const component = await wrapTestComponent('sw-product-list', { sync: true });

    return shallowMount(component, {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="smart-bar-actions" /></div>' },
                'sw-button-group': { template: '<div><slot /></div>' },
                'sw-context-button': { template: '<div><slot /></div>' },
            },
            mocks: { $route: { name: 'sw.product.index', query: {} } },
            provide: {
                repositoryFactory: {},
                numberRangeService: {},
                searchRankingService: {},
                filterFactory: {},
            },
        },
    });
}

describe('module/sw-product/page/sw-product-list analytics', () => {
    let wrapper: ReturnType<typeof shallowMount>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('adds an analytics ID only to the add digital product button', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('[data-analytics-id="sw_product.list.add_digital_product"]').exists()).toBe(true);
        expect(wrapper.get('.sw-product-list__add-physical-button').attributes('data-analytics-id')).toBeUndefined();
    });
});
