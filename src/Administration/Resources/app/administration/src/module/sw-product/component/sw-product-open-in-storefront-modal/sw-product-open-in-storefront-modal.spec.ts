/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import type { ProductStorefrontUrls } from '../../service/product-storefront-url.api.service';

const productStorefrontUrlApiService = { fetchProductUrls: jest.fn() };

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

function storefrontSalesChannel(overrides: Partial<ProductStorefrontUrls> = {}): ProductStorefrontUrls {
    return {
        reachable: true,
        domains: [
            {
                id: 'domain-id',
                url: 'https://shop.test',
                languageId: 'english-id',
                productUrl: 'https://shop.test/Shirt/SW10000',
            },
        ],
        ...overrides,
    };
}

async function createWrapper({
    salesChannels = [salesChannel()],
    loadedSalesChannel = storefrontSalesChannel() as ProductStorefrontUrls | null,
    product = {} as Record<string, unknown>,
    parentProduct = null as Record<string, unknown> | null,
    canReadConfig = true,
    hideCloseoutProducts = true,
    availableVariants = 0,
    hasUnsavedChanges = false,
    languageSearch = Promise.resolve([{ id: 'english-id', name: 'English' }]) as Promise<unknown>,
} = {}) {
    productStorefrontUrlApiService.fetchProductUrls = jest.fn().mockResolvedValue(loadedSalesChannel);
    const repository = {
        searchIds: jest.fn().mockResolvedValue({ total: availableVariants, data: [] }),
        search: jest.fn(() => languageSearch),
    };
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
                'mt-modal-root': {
                    name: 'mt-modal-root',
                    props: ['isOpen'],
                    emits: ['change'],
                    template:
                        '<div v-if="isOpen"><slot /><button class="mt-modal-root__dismiss" @click="$emit(\'change\', false)" /></div>',
                },
                'mt-modal': {
                    name: 'mt-modal',
                    props: ['title', 'subtitle'],
                    template: '<div><slot /><slot name="footer" /></div>',
                },
                'mt-modal-close': {
                    template: '<button class="mt-modal-close"><slot /></button>',
                },
                'mt-loader': true,
            },
            mocks: {
                $t: (key: string, params?: Record<string, string>) => [key, ...Object.values(params ?? {})].join(' '),
            },
            provide: {
                acl: { can: (privilege: string) => privilege !== 'system_config:read' || canReadConfig },
                repositoryFactory: { create: () => repository },
                systemConfigApiService,
                productStorefrontUrlApiService,
            },
        },
    });
    await flushPromises();

    return { wrapper, repository, systemConfigApiService };
}

async function select(wrapper: VueWrapper, salesChannelId: string | null) {
    await wrapper.setData({ selectedSalesChannelId: salesChannelId });
    await flushPromises();
}

function failingReasons(wrapper: VueWrapper): string[] {
    return wrapper.findAll('.sw-product-open-in-storefront-modal__check').map((check) => check.text());
}

const unreachable = { loadedSalesChannel: storefrontSalesChannel({ reachable: false }) };

describe('module/sw-product/component/sw-product-open-in-storefront-modal', () => {
    it('loads the only sales channel right away and links its product URL in a new tab', async () => {
        const { wrapper } = await createWrapper();

        const link = wrapper.find('a.sw-product-open-in-storefront-modal__domain');
        expect(productStorefrontUrlApiService.fetchProductUrls).toHaveBeenCalledTimes(1);
        expect(productStorefrontUrlApiService.fetchProductUrls).toHaveBeenCalledWith('product-id', 'sales-channel-id');
        expect(wrapper.findComponent({ name: 'mt-select' }).props('modelValue')).toBe('sales-channel-id');
        expect(failingReasons(wrapper)).toEqual([]);
        expect(link.attributes('href')).toBe('https://shop.test/Shirt/SW10000');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener');
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domains-headline').text()).toBe(
            'sw-product.openInStorefrontModal.labelDomains',
        );
        expect(link.find('.sw-product-open-in-storefront-modal__domain-header').text()).toBe('English');
        expect(link.find('.sw-product-open-in-storefront-modal__domain-url').text()).toBe('https://shop.test/Shirt/SW10000');
    });

    it('shows the product name as subtitle and keeps the product number out of title and description', async () => {
        const { wrapper } = await createWrapper({ product: { productNumber: 'SW10000' } });
        const modal = wrapper.findComponent({ name: 'mt-modal' });

        expect(modal.props('title')).toBe('sw-product.openInStorefrontModal.title');
        expect(modal.props('subtitle')).toBe('Shirt');
        expect(wrapper.find('.sw-product-open-in-storefront-modal__description').text()).toBe(
            'sw-product.openInStorefrontModal.description',
        );
    });

    it('shows the variation of a variant in the subtitle', async () => {
        const { wrapper } = await createWrapper({
            product: {
                variation: [
                    { group: 'Color', option: 'Blue' },
                    { group: 'Size', option: 'L' },
                ],
            },
        });

        expect(wrapper.findComponent({ name: 'mt-modal' }).props('subtitle')).toBe('Shirt (Color: Blue | Size: L)');
    });

    it('marks a domain without an http or https URL as disabled next to the linked domains', async () => {
        const { wrapper } = await createWrapper({
            loadedSalesChannel: storefrontSalesChannel({
                domains: [
                    {
                        id: 'domain-id',
                        url: 'https://shop.test',
                        languageId: 'english-id',
                        productUrl: 'https://shop.test/Shirt/SW10000',
                    },
                    { id: 'invalid-domain-id', url: 'ftp://shop.test', languageId: 'english-id', productUrl: null },
                ],
            }),
        });

        const links = wrapper.findAll('a.sw-product-open-in-storefront-modal__domain');
        const disabledDomain = wrapper.find('.sw-product-open-in-storefront-modal__domain.is--disabled');
        expect(links).toHaveLength(1);
        expect(links[0].attributes('href')).toBe('https://shop.test/Shirt/SW10000');
        expect(disabledDomain.attributes('aria-disabled')).toBe('true');
        expect(disabledDomain.find('.sw-product-open-in-storefront-modal__domain-url').text()).toBe('ftp://shop.test');
        expect(disabledDomain.find('.sw-product-open-in-storefront-modal__domain-hint').exists()).toBe(false);

        const banner = wrapper.find('.sw-product-open-in-storefront-modal__invalid-domains');
        expect(banner.classes()).toContain('mt-banner--attention');
        expect(banner.text()).toBe('sw-product.openInStorefrontModal.infoDomainsWithoutValidUrl ftp://shop.test 1');
    });

    it('loads nothing before a sales channel is selected', async () => {
        const { wrapper } = await createWrapper({
            salesChannels: [
                salesChannel({ id: 'b', translated: { name: 'Beta' } }),
                salesChannel({ id: 'a', translated: { name: 'Alpha' } }),
            ],
        });

        expect(productStorefrontUrlApiService.fetchProductUrls).not.toHaveBeenCalled();
        expect(wrapper.findComponent({ name: 'mt-select' }).props('hideClearableButton')).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-select' }).props('options')).toEqual([
            { value: 'a', label: 'Alpha' },
            { value: 'b', label: 'Beta' },
        ]);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__result').exists()).toBe(false);
    });

    it('names an inactive sales channel without asking the Storefront', async () => {
        const { wrapper } = await createWrapper({ salesChannels: [salesChannel({ active: false })] });

        expect(productStorefrontUrlApiService.fetchProductUrls).not.toHaveBeenCalled();
        expect(failingReasons(wrapper)).toEqual(['sw-product.openInStorefrontModal.checks.salesChannelActive']);
    });

    it.each([
        ['a sales channel without domain', { loadedSalesChannel: null }, 'hasDomain'],
        ['the general reason for an active product', unreachable, 'storefrontShowsProduct'],
        [
            'an inactive product, also when a variant inherits it',
            { ...unreachable, product: { active: null }, parentProduct: { active: false } },
            'productActive',
        ],
        [
            'a sold-out clearance sale product hidden by the sales channel',
            { ...unreachable, product: { isCloseout: true, available: false } },
            'inStock',
        ],
        [
            'the general reason when the sales channel shows sold-out clearance sale products',
            { ...unreachable, product: { isCloseout: true, available: false }, hideCloseoutProducts: false },
            'storefrontShowsProduct',
        ],
        [
            'the stock as possible reason when the setting cannot be read',
            {
                ...unreachable,
                product: { isCloseout: null, available: false },
                parentProduct: { isCloseout: true },
                canReadConfig: false,
            },
            'inStockPossibly',
        ],
        ['a parent without available variants', { ...unreachable, product: { childCount: 2 } }, 'inStock'],
        [
            'the general reason for a parent with an available variant',
            { ...unreachable, product: { childCount: 2 }, availableVariants: 1 },
            'storefrontShowsProduct',
        ],
    ])('names %s', async (_, options, reason) => {
        const { wrapper } = await createWrapper(options);

        expect(failingReasons(wrapper)).toEqual([`sw-product.openInStorefrontModal.checks.${reason}`]);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__check').classes()).toContain('mt-banner--attention');
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domain').exists()).toBe(false);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__domains-headline').exists()).toBe(false);
    });

    it('shows the domain URL without a language when the languages cannot be loaded', async () => {
        const { wrapper } = await createWrapper({ languageSearch: Promise.reject(new Error('Forbidden')) });

        const link = wrapper.find('a.sw-product-open-in-storefront-modal__domain');
        expect(link.find('.sw-product-open-in-storefront-modal__domain-header').exists()).toBe(false);
        expect(link.find('.sw-product-open-in-storefront-modal__domain-url').text()).toBe('https://shop.test/Shirt/SW10000');
    });

    it('does not check the stock when the Storefront shows the product', async () => {
        const { repository, systemConfigApiService } = await createWrapper({ product: { childCount: 2 } });

        expect(repository.searchIds).not.toHaveBeenCalled();
        expect(repository.search).toHaveBeenCalledTimes(1);
        expect(systemConfigApiService.getValues).not.toHaveBeenCalled();
    });

    it('hints at maintenance mode without hiding the product link', async () => {
        const { wrapper } = await createWrapper({ salesChannels: [salesChannel({ maintenance: true })] });

        const banner = wrapper.find('.sw-product-open-in-storefront-modal__maintenance');
        expect(banner.classes()).toContain('mt-banner--info');
        expect(banner.text()).toBe('sw-product.openInStorefrontModal.maintenanceHint');
        expect(wrapper.find('a.sw-product-open-in-storefront-modal__domain').exists()).toBe(true);
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

    it.each([
        ['an inactive sales channel', 'inactive'],
        ['no sales channel', null],
    ])('stops loading when the selection changes to %s during a request', async (_, nextSalesChannelId) => {
        const { wrapper } = await createWrapper({
            salesChannels: [salesChannel({ id: 'active' }), salesChannel({ id: 'inactive', active: false })],
        });
        productStorefrontUrlApiService.fetchProductUrls = jest.fn(() => new Promise(() => {}));

        await select(wrapper, 'active');
        expect(wrapper.find('mt-loader-stub').exists()).toBe(true);

        await select(wrapper, nextSalesChannelId);

        expect(wrapper.find('mt-loader-stub').exists()).toBe(false);
    });

    it('shows an error and stops loading when the product URLs cannot be loaded', async () => {
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        const { wrapper } = await createWrapper({
            salesChannels: [salesChannel(), salesChannel({ id: 'other-sales-channel-id' })],
        });
        productStorefrontUrlApiService.fetchProductUrls = jest.fn().mockRejectedValue(new Error('Server error'));

        await select(wrapper, 'sales-channel-id');

        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-product.openInStorefrontModal.notificationOpenInStorefrontError',
            }),
        );
        expect(wrapper.find('mt-loader-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-product-open-in-storefront-modal__result').exists()).toBe(false);
    });

    it('emits modal-close when the modal root closes', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-product-open-in-storefront-modal__footer .mt-modal-close').text()).toBe(
            'global.default.close',
        );

        await wrapper.find('.mt-modal-root__dismiss').trigger('click');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });
});
