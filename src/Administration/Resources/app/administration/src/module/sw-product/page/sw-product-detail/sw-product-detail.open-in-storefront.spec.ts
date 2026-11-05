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
    const product = { id: productId, visibilities };
    const repository = { hasChanges: jest.fn().mockReturnValue(hasChanges) };

    const wrapper = shallowMount(component, {
        props: { productId },
        global: {
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'router-view': true,
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

async function openedSalesChannelIds(wrapper: VueWrapper): Promise<string[]> {
    const modal = await openModal(wrapper);

    return (modal.props('salesChannels') as { id: string }[]).map(({ id }) => id);
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

    it.each([
        [
            'the Storefront sales channels the product is assigned to',
            [visibility('storefront'), visibility('headless', 'headless-type-id')],
            [],
            ['storefront'],
        ],
        [
            'the Storefront sales channels of the parent when a variant inherits them',
            [],
            [visibility('parent-storefront')],
            ['parent-storefront'],
        ],
    ])('passes %s', async (_, visibilities, parentVisibilities, expected) => {
        wrapper = await createWrapper({ visibilities, parentVisibilities });

        expect(await openedSalesChannelIds(wrapper)).toEqual(expected);
    });

    it('closes the modal when another product is opened', async () => {
        wrapper = await createWrapper();
        expect((await openModal(wrapper)).exists()).toBe(true);

        await wrapper.setProps({ productId: 'product-b' });

        expect(wrapper.findComponent(openInStorefrontModalStub).exists()).toBe(false);
    });

    it('shows no context menu for a product that is not saved yet', async () => {
        wrapper = await createWrapper({ productId: null });

        expect(wrapper.find('.sw-product-detail__context-button').exists()).toBe(false);
        expect(wrapper.find('.sw-product-detail__open-in-storefront').exists()).toBe(false);
    });
});
