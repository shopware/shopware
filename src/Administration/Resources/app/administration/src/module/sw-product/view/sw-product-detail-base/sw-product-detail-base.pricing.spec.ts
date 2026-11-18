/**
 * @sw-package inventory
 */
import type { VueWrapper } from '@vue/test-utils';
import { RouterLinkStub, shallowMount } from '@vue/test-utils';

const productIsNew = jest.fn();

async function createWrapper() {
    return shallowMount(await wrapTestComponent('sw-product-detail-base', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div><slot name="headerRight" /></div>',
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
        store.creationType = 'physical';
        store.modeSettings = ['prices'];
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('should link to advanced pricing for a saved product', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const link = wrapper.getComponent(RouterLinkStub);

        expect(link.props('to')).toEqual({
            name: 'sw.product.detail.prices',
            params: { id: 'productId' },
        });
    });

    it('should hide advanced pricing for a new product', async () => {
        productIsNew.mockReturnValue(true);

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-product-detail-base__advanced-prices').exists()).toBe(false);
    });
});
