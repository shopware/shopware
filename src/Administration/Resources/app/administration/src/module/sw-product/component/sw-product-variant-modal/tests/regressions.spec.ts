import { mount } from '@vue/test-utils';

/**
 * @sw-package inventory
 */

const VARIANT_GRID = '.sw-data-grid';

function createVariant() {
    return {
        id: 'variant-1',
        name: 'random product',
        translated: { name: 'random product' },
        price: [{ currencyId: 'currency-1', net: 24, gross: 24, linked: true }],
        options: [],
    };
}

describe('sw-product-variant-modal', () => {
    /**
     * @regression #22257 The variant overview modal showed blank prices or failed to render
     * when the system currency arrived after the variants.
     */
    it('loads and shows the variants only after the system currency', async () => {
        // ARRANGE
        let resolveCurrency: (currency: { id: string }) => void = () => {};
        const pendingCurrency = new Promise<{ id: string }>((resolve) => {
            resolveCurrency = resolve;
        });
        const searchVariants = jest.fn(() => Promise.resolve([createVariant()]));
        const wrapper = mount(await wrapTestComponent('sw-product-variant-modal', { sync: true }), {
            props: {
                productEntity: { id: 'product-1', translated: { name: 'random product' }, configuratorSettings: [] },
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => ({
                            get: () => (entity === 'currency' ? pendingCurrency : Promise.resolve()),
                            search: entity === 'product' ? searchVariants : () => Promise.resolve([]),
                        }),
                    },
                },
                stubs: {
                    'sw-modal': { template: '<div class="sw-modal"><slot /></div>' },
                    'sw-data-grid': { template: '<div class="sw-data-grid" />' },
                },
                mocks: {
                    $route: { meta: { $module: { icon: 'regular-content' } } },
                },
            },
        });
        await flushPromises();
        const searchedBeforeCurrency = searchVariants.mock.calls.length;
        const shownBeforeCurrency = wrapper.find(VARIANT_GRID).exists();

        // ACT
        resolveCurrency({ id: 'currency-1' });
        await flushPromises();

        // ASSERT
        expect(searchedBeforeCurrency).toBe(0);
        expect(shownBeforeCurrency).toBe(false);
        expect(searchVariants).toHaveBeenCalledTimes(1);
        expect(wrapper.find(VARIANT_GRID).exists()).toBe(true);

        wrapper.unmount();
    });
});
