/**
 * @sw-package inventory
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

const swPageStub = {
    name: 'sw-page',
    props: ['backRoute', 'backLabel'],
    template: '<div><slot name="content"></slot></div>',
};

async function createWrapper({ parentId = 'parent-id' as string | null, advancedMode = true } = {}) {
    const wrapper = shallowMount(await wrapTestComponent('sw-product-detail', { sync: true }), {
        props: { productId: 'variant-id' },
        global: {
            stubs: {
                'sw-page': swPageStub,
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'router-view': true,
                'mt-tabs': true,
            },
            mocks: { $route: { name: 'sw.product.detail.base', params: { id: 'variant-id' } } },
            provide: {
                repositoryFactory: { create: () => ({}) },
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
        product: { id: 'variant-id', parentId, translated: { name: 'Variant' } },
        advancedModeSetting: { value: { advancedMode: { enabled: advancedMode } } },
    });
    await flushPromises();

    return wrapper;
}

function backButton(wrapper: VueWrapper) {
    const page = wrapper.findComponent(swPageStub);

    return { route: page.props('backRoute') as unknown, label: page.props('backLabel') as unknown };
}

describe('module/sw-product/page/sw-product-detail back button', () => {
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

    it('leads a variant back to the variant overview of its main product', async () => {
        wrapper = await createWrapper();

        expect(backButton(wrapper)).toEqual({
            route: { name: 'sw.product.detail.variants', params: { id: 'parent-id' } },
            label: 'sw-product.general.backToMainProduct',
        });
    });

    it('leads a variant back to the general tab of its main product when the advanced mode is off', async () => {
        wrapper = await createWrapper({ advancedMode: false });

        expect(backButton(wrapper)).toEqual({
            route: { name: 'sw.product.detail.base', params: { id: 'parent-id' } },
            label: 'sw-product.general.backToMainProduct',
        });
    });

    it('keeps the default back button for a main product', async () => {
        wrapper = await createWrapper({ parentId: null });

        expect(backButton(wrapper)).toEqual({ route: null, label: '' });
    });
});
