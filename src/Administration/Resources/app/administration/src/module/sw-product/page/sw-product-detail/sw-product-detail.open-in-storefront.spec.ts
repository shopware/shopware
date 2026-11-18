/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

const openInStorefrontModalStub = {
    name: 'sw-product-open-in-storefront-modal',
    props: ['product', 'parentProduct', 'productName'],
    template: '<div class="sw-product-open-in-storefront-modal-stub"></div>',
};

async function createWrapper({ productId = 'product-a' as string | null } = {}) {
    const component = await wrapTestComponent('sw-product-detail', { sync: true });
    const product = { id: productId, name: 'Shirt', getOrigin: () => ({ id: productId }) };
    const parentProduct = { id: 'parent', getOrigin: () => ({ id: 'parent' }) };

    const wrapper = shallowMount(component, {
        props: { productId },
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'router-view': true,
                'mt-tabs': true,
                'mt-dropdown-menu-root': { template: '<div><slot /></div>' },
                'mt-dropdown-menu-trigger': { template: '<div><slot /></div>' },
                'mt-dropdown-menu-portal': { template: '<div><slot /></div>' },
                'mt-action-menu': { template: '<div><slot /></div>' },
                'mt-action-menu-item': {
                    emits: ['select'],
                    template: '<button @click="$emit(\'select\')"><slot /></button>',
                },
                'sw-product-open-in-storefront-modal': openInStorefrontModalStub,
            },
            mocks: { $route: { name: 'sw.product.detail.base', params: { id: productId } } },
            provide: {
                repositoryFactory: { create: () => ({ hasChanges: () => false }) },
                mediaService: {},
                numberRangeService: {},
                seoUrlService: {},
                systemConfigApiService: {},
                customFieldDataProviderService: {},
                entityValidationService: {},
            },
        },
    });

    Object.assign(Shopware.Store.get('swProductDetail'), { product, parentProduct });
    await flushPromises();

    return { wrapper, product, parentProduct };
}

async function openModal(wrapper: VueWrapper) {
    await wrapper.find('.sw-product-detail__open-in-storefront').trigger('click');
    await flushPromises();

    return wrapper.findComponent(openInStorefrontModalStub);
}

describe('module/sw-product/page/sw-product-detail open in Storefront', () => {
    let wrapper: VueWrapper;

    beforeAll(() => {
        Shopware.Component.override('sw-product-detail', {
            methods: { createdComponent() {} },
        });
    });

    beforeEach(() => {
        Shopware.Store.get('swProductDetail').$reset();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('opens the modal for the product and its parent', async () => {
        let product: { id: string | null };
        let parentProduct: { id: string };
        ({ wrapper, product, parentProduct } = await createWrapper());

        const modal = await openModal(wrapper);

        expect(modal.props('product')).toEqual(product);
        expect(modal.props('parentProduct')).toEqual(parentProduct);
        expect(modal.props('productName')).toBe('Shirt');
    });

    it('closes the modal when another product is opened', async () => {
        ({ wrapper } = await createWrapper());
        expect((await openModal(wrapper)).exists()).toBe(true);

        await wrapper.setProps({ productId: 'product-b' });

        expect(wrapper.findComponent(openInStorefrontModalStub).exists()).toBe(false);
    });

    it('shows no context menu for a product that is not saved yet', async () => {
        ({ wrapper } = await createWrapper({ productId: null }));

        expect(wrapper.find('.sw-product-detail__context-button').exists()).toBe(false);
        expect(wrapper.find('.sw-product-detail__open-in-storefront').exists()).toBe(false);
    });
});
