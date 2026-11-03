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

    it('adds matching analytics IDs to the physical and digital product creation actions', async () => {
        wrapper = await createWrapper();

        const physicalProductAction = wrapper.find('[data-analytics-id="sw_product.list.add_physical_product"]');
        const digitalProductAction = wrapper.find('[data-analytics-id="sw_product.list.add_digital_product"]');

        expect(physicalProductAction.exists()).toBe(true);
        expect(digitalProductAction.exists()).toBe(true);
    });
});
