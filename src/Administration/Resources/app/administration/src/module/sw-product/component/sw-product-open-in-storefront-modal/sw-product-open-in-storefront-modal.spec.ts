/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import type ProductStorefrontUrlApiService from '../../service/product-storefront-url.api.service';
import type { ProductStorefrontSalesChannel } from '../../service/product-storefront-url.api.service';

const productStorefrontUrlApiService = { fetchSalesChannel: jest.fn() };

function salesChannel(overrides: Record<string, unknown> = {}): Entity<'sales_channel'> {
    return {
        id: 'sales-channel-id',
        name: 'Storefront',
        translated: { name: 'Storefront' },
        active: true,
        maintenance: false,
        ...overrides,
    } as unknown as Entity<'sales_channel'>;
}

function storefrontSalesChannel(overrides: Partial<ProductStorefrontSalesChannel> = {}): ProductStorefrontSalesChannel {
    return {
        id: 'sales-channel-id',
        reachable: true,
        domains: [
            {
                id: 'domain-id',
                url: 'https://shop.test',
                productUrl: 'https://shop.test/Shirt/SW10000',
            },
        ],
        ...overrides,
    };
}

async function createWrapper({
    salesChannels = [salesChannel()],
    loadedSalesChannel = storefrontSalesChannel() as ProductStorefrontSalesChannel | null,
    product = {} as Record<string, unknown>,
    parentProduct = null as Record<string, unknown> | null,
    canReadConfig = true,
    hideCloseoutProducts = true,
    availableVariants = 0,
    hasUnsavedChanges = false,
} = {}) {
    productStorefrontUrlApiService.fetchSalesChannel = jest.fn().mockResolvedValue(loadedSalesChannel);
    const repository = { searchIds: jest.fn().mockResolvedValue({ total: availableVariants, data: [] }) };
    const systemConfigApiService = {
        getValues: jest.fn().mockResolvedValue({ 'core.listing.hideCloseoutProductsWhenOutOfStock': hideCloseoutProducts }),
    };

    const wrapper = mount(await wrapTestComponent('sw-product-open-in-storefront-modal', { sync: true }), {
        props: {
            product: { id: 'product-id', active: true, childCount: 0, ...product },
            parentProduct,
            productName: 'Shirt',
            salesChannels,
            hasUnsavedChanges,
        },
        global: {
            stubs: {
                'sw-modal': await wrapTestComponent('sw-modal', { sync: true }),
                'sw-external-link': await wrapTestComponent('sw-external-link', { sync: true }),
                'sw-loader': true,
            },
            provide: {
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
                acl: { can: (privilege: string) => privilege !== 'system_config:read' || canReadConfig },
                repositoryFactory: { create: () => repository },
                systemConfigApiService,
            },
        },
    });

    return { wrapper, repository, systemConfigApiService };
}

async function select(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper'], salesChannelId: string) {
    await wrapper.setData({ selectedSalesChannelId: salesChannelId });
    await flushPromises();
}

function failingReasons(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper']): string[] {
    return wrapper.findAll('.sw-product-open-in-storefront-modal__check').map((check) => check.text());
}

describe('module/sw-product/component/sw-product-open-in-storefront-modal', () => {
    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- registers a test double for the module service
        Shopware.Service().register(
            'productStorefrontUrlApiService',
            () => productStorefrontUrlApiService as unknown as ProductStorefrontUrlApiService,
        );
    });

    it('loads nothing before a sales channel is selected', async () => {
        const { wrapper } = await createWrapper({
            salesChannels: [
                salesChannel({ id: 'b', translated: { name: 'Beta' } }),
                salesChannel({ id: 'a', translated: { name: 'Alpha' } }),
            ],
        });
        await flushPromises();

        expect(productStorefrontUrlApiService.fetchSalesChannel).not.toHaveBeenCalled();
        expect(wrapper.findComponent({ name: 'mt-select' }).props('options')).toEqual([
            { value: 'a', label: 'Alpha' },
            { value: 'b', label: 'Beta' },
        ]);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__sales-channel').exists()).toBe(false);
    });

    it('links the product URL of the selected sales channel in a new tab', async () => {
        const { wrapper } = await createWrapper();

        await select(wrapper, 'sales-channel-id');

        const link = wrapper.find('.sw-product-open-in-storefront-modal__domain a');
        expect(productStorefrontUrlApiService.fetchSalesChannel).toHaveBeenCalledWith('product-id', 'sales-channel-id');
        expect(failingReasons(wrapper)).toEqual([]);
        expect(link.attributes('href')).toBe('https://shop.test/Shirt/SW10000');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener');
    });

    it('names an inactive sales channel without asking the Storefront', async () => {
        const { wrapper } = await createWrapper({ salesChannels: [salesChannel({ active: false })] });

        await select(wrapper, 'sales-channel-id');

        expect(productStorefrontUrlApiService.fetchSalesChannel).not.toHaveBeenCalled();
        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.salesChannelActive']);
    });

    it('names a sales channel without domain', async () => {
        const { wrapper } = await createWrapper({ loadedSalesChannel: null });

        await select(wrapper, 'sales-channel-id');

        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.hasDomain']);
    });

    it('names the general reason when the Storefront does not show an active product', async () => {
        const { wrapper } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({
                reachable: false,
                domains: [{ id: 'domain-id', url: 'https://shop.test', productUrl: null }],
            }),
        });

        await select(wrapper, 'sales-channel-id');

        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.storefrontShowsProduct']);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domain a').exists()).toBe(false);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domain-url').text()).toBe('https://shop.test');
    });

    it('names an inactive product, also when a variant inherits it', async () => {
        const { wrapper } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({ reachable: false }),
            product: { active: null },
            parentProduct: { active: false },
        });

        await select(wrapper, 'sales-channel-id');

        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.productActive']);
    });

    it('names a sold-out clearance sale product for a sales channel that hides them', async () => {
        const { wrapper, systemConfigApiService } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({ reachable: false }),
            product: { isCloseout: true, available: false },
        });

        await select(wrapper, 'sales-channel-id');

        expect(systemConfigApiService.getValues).toHaveBeenCalledWith('core.listing', 'sales-channel-id', { inherit: true });
        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.inStock']);
    });

    it('does not name the stock when the sales channel shows sold-out clearance sale products', async () => {
        const { wrapper } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({ reachable: false }),
            product: { isCloseout: true, available: false },
            hideCloseoutProducts: false,
        });

        await select(wrapper, 'sales-channel-id');

        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.storefrontShowsProduct']);
    });

    it('names the stock as possible reason when the setting cannot be read', async () => {
        const { wrapper, systemConfigApiService } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({ reachable: false }),
            product: { isCloseout: null, available: false },
            parentProduct: { isCloseout: true },
            canReadConfig: false,
        });

        await select(wrapper, 'sales-channel-id');

        expect(systemConfigApiService.getValues).not.toHaveBeenCalled();
        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.inStockPossibly']);
    });

    it('checks the variants of a parent for available clearance sale products', async () => {
        const { wrapper, repository } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({ reachable: false }),
            product: { childCount: 2 },
            availableVariants: 0,
        });

        await select(wrapper, 'sales-channel-id');

        expect(repository.searchIds).toHaveBeenCalledWith(
            expect.objectContaining({ limit: 1 }),
            expect.objectContaining({ inheritance: true }),
        );
        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.inStock']);
    });

    it('does not check the stock when the Storefront shows the product', async () => {
        const { wrapper, repository, systemConfigApiService } = await createWrapper({ product: { childCount: 2 } });

        await select(wrapper, 'sales-channel-id');

        expect(repository.searchIds).not.toHaveBeenCalled();
        expect(systemConfigApiService.getValues).not.toHaveBeenCalled();
    });

    it('hints at maintenance mode without hiding the product link', async () => {
        const { wrapper } = await createWrapper({ salesChannels: [salesChannel({ maintenance: true })] });

        await select(wrapper, 'sales-channel-id');

        expect(wrapper.find('.sw-product-open-in-storefront-modal__maintenance').text()).toBe(
            'sw-product.openInStorefrontModal.maintenanceHint',
        );
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domain a').exists()).toBe(true);
    });

    it('warns that the Storefront shows the saved state when there are unsaved changes', async () => {
        const { wrapper } = await createWrapper({ hasUnsavedChanges: true });

        expect(wrapper.find('.sw-product-open-in-storefront-modal__unsaved-changes').exists()).toBe(true);
    });

    it('informs when the product is not assigned to a Storefront sales channel', async () => {
        const { wrapper } = await createWrapper({ salesChannels: [] });

        expect(wrapper.find('.sw-product-open-in-storefront-modal__no-sales-channels').exists()).toBe(true);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__sales-channel-select').exists()).toBe(false);
    });

    it('emits modal-close when it is closed', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.sw-modal__footer button').trigger('click');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
