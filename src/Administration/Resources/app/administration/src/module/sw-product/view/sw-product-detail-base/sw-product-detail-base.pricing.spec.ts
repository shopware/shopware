/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import { RouterLinkStub, shallowMount } from '@vue/test-utils';

const productIsNew = jest.fn();

async function createWrapper(props: Record<string, unknown> = {}) {
    return shallowMount(await wrapTestComponent('sw-product-detail-base', { sync: true }), {
        props,
        global: {
            mocks: {
                $route: { params: { id: 'productId' } },
            },
            stubs: {
                'mt-card': {
                    template: '<div><slot /><slot name="headerRight" /></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot /></div>',
                },
                'mt-button': {
                    props: ['is', 'to'],
                    template: '<component :is="is" :to="to"><slot /></component>',
                },
                'router-link': RouterLinkStub,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve({ first: () => ({ folder: {} }) }),
                    }),
                },
                mediaDefaultFolderService: {
                    getDefaultFolderId: () => Promise.resolve(null),
                },
            },
        },
    });
}

describe('sw-product-detail-base pricing', () => {
    let wrapper: VueWrapper;

    beforeEach(() => {
        productIsNew.mockReset().mockReturnValue(false);
        const store = Shopware.Store.get('swProductDetail');
        store.$reset();
        store.product.id = 'productId' as typeof store.product.id;
        store.product.isNew = productIsNew;
        // A digital product skips the deprecated `productStates` fallback of the download card,
        // which logs an error in tests that activate the major flag.
        store.product.type = 'digital';
        store.creationType = 'digital';
        store.modeSettings = ['prices'];
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should link to advanced pricing from the card header for a saved product',
        async () => {
            wrapper = await createWrapper();
            await flushPromises();

            const link = wrapper.getComponent(RouterLinkStub);

            expect(link.classes()).toContain('sw-product-detail-base__advanced-prices');
            expect(link.props('to')).toEqual({
                name: 'sw.product.detail.prices',
                params: { id: 'productId' },
            });
            expect(wrapper.find('.sw-container').exists()).toBe(false);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should hide advanced pricing for a new product', async () => {
        productIsNew.mockReturnValue(true);

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-detail-base__advanced-prices').exists()).toBe(false);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy advanced prices link.
    it.deprecated('v6.8.0.0')('should render the advanced pricing link below the price form', async () => {
        wrapper = await createWrapper({ productId: 'productId' });
        await flushPromises();

        const link = wrapper.get('.sw-container').getComponent(RouterLinkStub);

        expect(link.props('to')).toEqual({
            name: 'sw.product.detail.prices',
            params: { id: 'productId' },
        });
        expect(wrapper.find('.sw-product-detail-base__advanced-prices').exists()).toBe(false);
    });
});
