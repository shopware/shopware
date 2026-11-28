/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';

const openInStorefrontModalStub = defineComponent({
    name: 'sw-product-open-in-storefront-modal',
    props: ['product', 'parentProduct', 'productName'],
    emits: ['modal-close'],
    template: '<div class="sw-product-open-in-storefront-modal-stub"></div>',
});

const products = [
    { id: 'product-a', name: 'Product A' },
    { id: 'product-b', name: 'Product B' },
];

async function createWrapper() {
    const productRepository = { search: jest.fn() };

    const wrapper = shallowMount(await wrapTestComponent('sw-product-list', { sync: true }), {
        data: () => ({ products, total: products.length }),
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content" /></div>' },
                'sw-entity-listing': {
                    props: ['dataSource'],
                    template: `
                        <div>
                            <div v-for="item in dataSource" :key="item.id" :class="'row--' + item.id">
                                <slot name="more-actions" :item="item" />
                            </div>
                        </div>`,
                },
                'sw-context-menu-item': { template: '<button class="sw-context-menu-item"><slot /></button>' },
                'sw-product-open-in-storefront-modal': openInStorefrontModalStub,
            },
            mocks: { $route: { name: 'sw.product.index', query: {} } },
            provide: {
                repositoryFactory: { create: () => productRepository },
                numberRangeService: {},
                searchRankingService: {},
                filterFactory: {},
            },
        },
    });
    await flushPromises();

    return { wrapper, productRepository };
}

function openInStorefrontAction(wrapper: VueWrapper, productId: string) {
    return wrapper.find(`.row--${productId} .sw-product-list-grid__open-in-storefront-action`);
}

async function selectOpenInStorefront(wrapper: VueWrapper, productId: string) {
    await openInStorefrontAction(wrapper, productId).trigger('click');
    await flushPromises();

    return wrapper.findComponent(openInStorefrontModalStub);
}

describe('module/sw-product/page/sw-product-list open in Storefront', () => {
    let wrapper: VueWrapper;

    beforeAll(() => {
        Shopware.Component.override('sw-product-list', {
            methods: { getList() {} },
        });
    });

    afterEach(() => {
        wrapper?.unmount();
        global.activeAclRoles = [];
    });

    it('offers the action in the context menu of each row to product viewers', async () => {
        global.activeAclRoles = ['product.viewer'];
        ({ wrapper } = await createWrapper());

        products.forEach(({ id }) => {
            expect(openInStorefrontAction(wrapper, id).text()).toBe('sw-product.detail.buttonOpenInStorefront');
        });
    });

    it('opens the modal for the product of the row without loading anything itself', async () => {
        let productRepository: { search: jest.Mock };
        ({ wrapper, productRepository } = await createWrapper());

        const modal = await selectOpenInStorefront(wrapper, 'product-b');

        expect(modal.props()).toEqual({
            product: products[1],
            parentProduct: undefined,
            productName: 'Product B',
        });
        expect(productRepository.search).not.toHaveBeenCalled();
    });

    it('closes the modal', async () => {
        ({ wrapper } = await createWrapper());

        const modal = await selectOpenInStorefront(wrapper, 'product-a');
        modal.vm.$emit('modal-close');
        await flushPromises();

        expect(wrapper.findComponent(openInStorefrontModalStub).exists()).toBe(false);
    });
});
