/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

const openInStorefrontModalStub = {
    name: 'sw-product-open-in-storefront-modal',
    props: ['salesChannels', 'hasUnsavedChanges'],
    template: '<div class="sw-product-open-in-storefront-modal-stub"></div>',
};

function visibility(salesChannelId: string, typeId: string = Shopware.Defaults.storefrontSalesChannelTypeId) {
    return { salesChannel: { id: salesChannelId, typeId } };
}

async function createWrapper({
    productId = 'product-a' as string | null,
    hasChanges = false,
    visibilities = [visibility('sales-channel-id')] as ReturnType<typeof visibility>[],
    parentVisibilities = [] as ReturnType<typeof visibility>[],
} = {}) {
    const component = await wrapTestComponent('sw-product-detail', { sync: true });
    const product = { id: productId, childCount: 0, visibilities, isNew: () => productId === null };
    const repository = {
        get: jest.fn().mockResolvedValue(product),
        hasChanges: jest.fn().mockReturnValue(hasChanges),
    };

    const wrapper = shallowMount(component, {
        props: { productId },
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'router-view': true,
                'mt-button': {
                    props: ['disabled'],
                    emits: ['click'],
                    template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
                },
                'sw-product-open-in-storefront-modal': openInStorefrontModalStub,
            },
            mocks: { $route: { name: 'sw.product.detail.base', params: { id: productId } } },
            provide: {
                repositoryFactory: { create: () => repository },
                mediaService: {},
                numberRangeService: {},
                seoUrlService: {},
                systemConfigApiService: {},
                customFieldDataProviderService: {},
                entityValidationService: {},
            },
        },
    });

    Object.assign(Shopware.Store.get('swProductDetail'), {
        product,
        parentProduct: { id: 'parent', visibilities: parentVisibilities },
    });
    await flushPromises();

    return wrapper;
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

    it('opens the modal with the unsaved changes of the product', async () => {
        wrapper = await createWrapper({ hasChanges: true });

        const modal = await openModal(wrapper);

        expect(modal.exists()).toBe(true);
        expect(modal.props('hasUnsavedChanges')).toBe(true);
    });

    it('passes the Storefront sales channels the product is assigned to', async () => {
        wrapper = await createWrapper({
            visibilities: [visibility('storefront'), visibility('headless', 'headless-type-id')],
        });

        const modal = await openModal(wrapper);

        expect((modal.props('salesChannels') as { id: string }[]).map(({ id }) => id)).toEqual(['storefront']);
    });

    it('passes the Storefront sales channels of the parent when a variant inherits them', async () => {
        wrapper = await createWrapper({ visibilities: [], parentVisibilities: [visibility('parent-storefront')] });

        const modal = await openModal(wrapper);

        expect((modal.props('salesChannels') as { id: string }[]).map(({ id }) => id)).toEqual(['parent-storefront']);
    });

    it('closes the modal when another product is opened', async () => {
        wrapper = await createWrapper();
        await openModal(wrapper);

        await wrapper.setProps({ productId: 'product-b' });

        expect(wrapper.findComponent(openInStorefrontModalStub).exists()).toBe(false);
    });

    it('disables the button for a product that is not saved yet', async () => {
        wrapper = await createWrapper({ productId: null });

        expect(wrapper.find('.sw-product-detail__open-in-storefront').attributes('disabled')).toBeDefined();
    });

    it('enables the button for a saved product', async () => {
        wrapper = await createWrapper();

        expect(wrapper.find('.sw-product-detail__open-in-storefront').attributes('disabled')).toBeUndefined();
    });
});
