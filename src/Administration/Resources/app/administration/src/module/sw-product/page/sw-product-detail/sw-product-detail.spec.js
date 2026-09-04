/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const advancedModeSettings = {
    value: {
        advancedMode: {
            label: 'sw-product.general.textAdvancedMode',
            enabled: true,
        },
        settings: [
            {
                key: 'general_information',
                label: 'sw-product.detailBase.cardTitleProductInfo',
                enabled: true,
                name: 'general',
            },
            {
                key: 'prices',
                label: 'sw-product.detailBase.cardTitlePrices',
                enabled: true,
                name: 'general',
            },
            {
                key: 'deliverability',
                label: 'sw-product.detailBase.cardTitleDeliverabilityInfo',
                enabled: true,
                name: 'general',
            },
            {
                key: 'visibility_structure',
                label: 'sw-product.detailBase.cardTitleVisibilityStructure',
                enabled: true,
                name: 'general',
            },
            {
                key: 'labelling',
                label: 'sw-product.detailBase.cardTitleSettings',
                enabled: true,
                name: 'general',
            },
        ],
    },
};

const defaultSalesChannelData = {
    'core.defaultSalesChannel.active': false,
    'core.defaultSalesChannel.salesChannel': [
        '98432def39fc4624b33213a56b8c944d',
    ],
    'core.defaultSalesChannel.visibility': {
        '98432def39fc4624b33213a56b8c944d': 10,
    },
};

describe('module/sw-product/page/sw-product-detail', () => {
    async function createWrapper(
        searchFunction = () => Promise.resolve([]),
        getFunction = () => {
            return Promise.resolve({ variation: [] });
        },
        productId = '1234',
        { featureActive = false, routeName = 'sw.product.detail.base', routerPush = jest.fn() } = {},
    ) {
        return mount(await wrapTestComponent('sw-product-detail', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: routeName,
                        params: {
                            id: productId,
                        },
                    },
                    $router: {
                        push: routerPush,
                    },
                },
                provide: {
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                    numberRangeService: {
                        reserve: () => Promise.resolve({ number: 1 }),
                    },
                    seoUrlService: {},
                    mediaService: {},
                    repositoryFactory: {
                        create: (entity) => ({
                            create: () => {
                                if (entity === 'product') {
                                    return {
                                        id: '1',
                                        parentId: '1',
                                        properties: [],
                                        visibilities: [],
                                        isNew: () => true,
                                    };
                                }

                                return {};
                            },
                            search: searchFunction,
                            searchIds: () => Promise.resolve({ data: [] }),
                            get: async (...args) => {
                                const product = await getFunction(...args);

                                if (product && !product._origin) {
                                    product._origin = {};
                                }

                                return product;
                            },
                            hasChanges: () => true,
                            save: () => Promise.resolve({}),
                        }),
                    },
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.tax.defaultTaxRate': '',
                            }),
                        getValues: () => Promise.resolve(defaultSalesChannelData),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    entityValidationService: {
                        validate: (entity, customValidator) => {
                            let errors = [];
                            if (customValidator) {
                                errors = customValidator(errors, entity);
                            }

                            return errors.length < 1;
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `<div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">
                                <div class="sw-tabs"></div>
                            </slot>
                            <slot></slot>
                        </div>`,
                    },
                    'sw-product-variant-info': true,
                    'sw-button-group': true,
                    'sw-button-process': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-language-info': true,
                    'router-view': true,

                    'sw-context-menu-divider': true,
                    'sw-checkbox-field': true,
                    'sw-product-settings-mode': await wrapTestComponent('sw-product-settings-mode', { sync: true }),
                    'sw-loader': true,
                    'sw-tabs': {
                        name: 'sw-tabs',
                        props: {
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                        },
                        template: '<div class="sw-tabs"><slot /></div>',
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot /></div>',
                        props: [
                            'route',
                            'title',
                            'hasError',
                        ],
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'sw-inheritance-warning': true,
                    'router-link': true,
                    'sw-product-detail': await wrapTestComponent('sw-product-detail'),
                    'sw-extension-component-section': true,
                    'sw-product-clone-modal': true,
                },
            },
            props: {
                productId,
            },
        });
    }

    let wrapper;

    beforeAll(() => {
        Shopware.Store.unregister('cmsPage');
        Shopware.Store.register({
            id: 'cmsPage',
            actions: {
                resetCmsPageState: () => {},
            },
        });
    });

    beforeEach(async () => {
        jest.restoreAllMocks();
        jest.spyOn(Shopware.Service('userConfigService'), 'search').mockResolvedValue({ data: {} });
        jest.spyOn(Shopware.Service('userConfigService'), 'upsert').mockResolvedValue();

        wrapper = await createWrapper();

        Shopware.Store.get('swProductDetail').setLengthUnit = jest.fn();
        Shopware.Store.get('swProductDetail').setWeightUnit = jest.fn();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should show item tabs', async () => {
        await wrapper.setProps({
            productId: '1234',
        });
        const tabItemClassName = [
            '.sw-product-detail__tab-advanced-prices',
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews',
        ];

        await nextTick();

        tabItemClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should flag the product as loading before awaiting the measurement units', async () => {
        await wrapper.unmount();
        wrapper = null;

        let resolveSearch;
        Shopware.Service('userConfigService').search.mockReturnValue(
            new Promise((resolve) => {
                resolveSearch = resolve;
            }),
        );

        Shopware.Store.get('swProductDetail').$reset();
        expect(Shopware.Store.get('swProductDetail').loading.product).toBe(false);

        wrapper = await createWrapper();

        expect(Shopware.Store.get('swProductDetail').loading.product).toBe(true);

        resolveSearch({ data: {} });
        await flushPromises();
    });

    it('should not keep the product loading when the measurement units cannot be loaded', async () => {
        await wrapper.unmount();
        wrapper = null;

        Shopware.Service('userConfigService').search.mockImplementation((keys) => {
            if (keys.includes('measurement.preferenceUnits')) {
                return Promise.reject(new Error('Request failed'));
            }

            return Promise.resolve({ data: {} });
        });

        Shopware.Store.get('swProductDetail').$reset();

        wrapper = await createWrapper();
        await flushPromises();

        expect(Shopware.Store.get('swProductDetail').loading.product).toBe(false);
    });

    it('should redirect to product listing when product no longer exists', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve(null),
        );

        await wrapper.setProps({
            productId: 'missing-product-id',
        });
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$router.push.mockClear();

        Shopware.Store.get('swProductDetail').product = {
            id: 'stale-product-id',
            parentId: 'stale-parent-id',
        };
        Shopware.Store.get('swProductDetail').parentProduct = {
            id: 'stale-parent-id',
        };

        await wrapper.vm.loadProduct();
        await nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-product.detail.messageProductNotFound',
        });
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.product.index',
        });
        expect(Shopware.Store.get('swProductDetail').product).toEqual({});
        expect(Shopware.Store.get('swProductDetail').parentProduct).toEqual({});
        expect(wrapper.find('.sw-card-view').exists()).toBe(false);
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', () => {
        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-product-detail');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor route tabs when the major feature flag is active', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', { featureActive: true });
        await flushPromises();

        Shopware.Store.get('swProductDetail').product = { parentId: null };
        Shopware.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-product-detail');
        expect(tabs.props('defaultItem')).toBe('sw.product.detail.base');
        expect(items.map(({ label, name }) => ({ label, name }))).toEqual([
            {
                label: 'sw-product.detail.tabGeneral',
                name: 'sw.product.detail.base',
            },
            {
                label: 'sw-product.detail.tabSpecifications',
                name: 'sw.product.detail.specifications',
            },
            {
                label: 'sw-product.detail.tabAdvancedPrices',
                name: 'sw.product.detail.prices',
            },
            {
                label: 'sw-product.detail.tabVariation',
                name: 'sw.product.detail.variants',
            },
            {
                label: 'sw-product.detail.tabLayout',
                name: 'sw.product.detail.layout',
            },
            {
                label: 'sw-product.detail.tabSeo',
                name: 'sw.product.detail.seo',
            },
            {
                label: 'sw-product.detail.tabCrossSelling',
                name: 'sw.product.detail.crossSelling',
            },
            {
                label: 'sw-product.detail.tabReviews',
                name: 'sw.product.detail.reviews',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.find('.sw-product-detail__tab-general').exists()).toBe(false);
    });

    it('should show the layout meteor tab for variant products', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', { featureActive: true });
        await flushPromises();

        Shopware.Store.get('swProductDetail').product = { parentId: 'parent-id' };
        Shopware.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const tabNames = wrapper
            .getComponent({ name: 'mt-tabs' })
            .props('items')
            .map((item) => item.name);

        expect(tabNames).toContain('sw.product.detail.prices');
        expect(tabNames).toContain('sw.product.detail.layout');
        expect(tabNames).toContain('sw.product.detail.seo');
        expect(tabNames).not.toContain('sw.product.detail.variants');
    });

    it('should navigate when a meteor route tab is selected', async () => {
        const routerPush = jest.fn();

        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', {
            featureActive: true,
            routerPush,
        });
        await flushPromises();

        Shopware.Store.get('swProductDetail').product = { parentId: null };
        Shopware.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const pricesTab = wrapper
            .getComponent({ name: 'mt-tabs' })
            .props('items')
            .find((item) => item.name === 'sw.product.detail.prices');

        pricesTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.product.detail.prices',
            params: { id: '1234' },
        });
    });

    it('should show item tabs when advanced mode deactivate', async () => {
        Shopware.Store.get('swProductDetail').product = { parentId: '' };
        await wrapper.setProps({
            productId: '1234',
        });

        Shopware.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSettings.value,
                advancedMode: {
                    enabled: false,
                },
            },
        };

        const tabItemClassName = [
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews',
        ];

        await nextTick();

        tabItemClassName.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });

    it('should show Advance mode setting on the variant product page', async () => {
        await flushPromises();
        Shopware.Store.get('swProductDetail').product = {
            ...wrapper.vm.product,
            parentId: 'parent-id',
        };

        await wrapper.setProps({
            productId: '1234',
        });

        const contextButton = wrapper.find('.sw-product-settings-mode');
        expect(contextButton.exists()).toBeFalsy();

        const visibleTabItem = [
            '.sw-product-detail__tab-layout',
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews',
        ];

        const invisibleTabItem = [
            '.sw-product-detail__tab-variants',
        ];

        visibleTabItem.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBeFalsy();
        });

        invisibleTabItem.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });

    it('should always show the correct menu, even with the defaults not matching the userConfig', async () => {
        const keys = [
            'general_information',
            'prices',
            'deliverability',
        ];
        const mockKey = 'mock_key_without_result';
        const settings = [...keys].map((key) => {
            return {
                enabled: false,
                key,
                label: key,
                name: 'general',
            };
        });
        await wrapper.vm.$nextTick();

        settings.forEach((entry) => {
            expect(entry.enabled).toBe(!keys.includes(entry.key));
        });

        keys.forEach((key) => {
            expect(settings.some((entry) => entry.key === key)).toBe(true);
        });

        expect(settings.some((entry) => entry.key === mockKey)).toBeFalsy();
    });

    it('should set purchasePrices to default value when given purchasePrices are empty', async () => {
        await wrapper.vm.$nextTick();
        wrapper.unmount();
        wrapper = await createWrapper(() =>
            Promise.resolve([
                {
                    id: '123',
                    name: 'EUR',
                },
            ]),
        );

        await flushPromises();
        await nextTick();

        expect(wrapper.vm.product.purchasePrices).toStrictEqual([
            {
                currencyId: undefined,
                gross: 0,
                net: 0,
                linked: true,
            },
        ]);
    });

    it('should validate and clear listPrices/regulationPrices on save', async () => {
        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.product.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.product.prices = [];
        wrapper.vm.product.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.onSave();

        expect(wrapper.vm.product.price).toStrictEqual([
            {
                currencyId: undefined,
                gross: 100,
                net: 84.034,
                linked: true,
                listPrice: null,
                regulationPrice: null,
            },
        ]);
        await flushPromises();
    });

    it('should show correct config when there is system config data', async () => {
        await flushPromises();
        await wrapper.unmount();

        wrapper = await createWrapper(
            () => {
                return Promise.resolve([
                    {
                        id: '98432def39fc4624b33213a56b8c944d',
                        name: 'Headless',
                    },
                ]);
            },
            undefined,
            null,
        );

        await flushPromises();
        await flushPromises();
        expect(wrapper.vm.product.visibilities).toHaveLength(1);
    });

    it('should run custom validation service and handle errors', async () => {
        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        Shopware.Store.get('swProductDetail').product = {
            isNew: jest.fn(() => true),
            prices: [],
            price: [
                {
                    currencyId: undefined,
                    linked: true,
                    gross: 100,
                    net: 84.034,
                    listPrice: {
                        currencyId: undefined,
                        linked: true,
                        gross: 0,
                        net: 0,
                    },
                    regulationPrice: {
                        currencyId: undefined,
                        linked: true,
                        gross: 0,
                        net: 0,
                    },
                },
            ],
        };

        // make it a download product which requires downloads
        if (!Shopware.Feature.isActive('v6.8.0.0')) {
            Shopware.Store.get('swProductDetail').creationStates = 'is-download';
        }

        Shopware.Store.get('swProductDetail').creationType = 'digital';

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });
        wrapper.vm.onSave();

        // save shouldn't finish successfully (nothing should be sent to the server - no saveProduct call)
        expect(wrapper.vm.saveProduct.mock.calls).toHaveLength(0);
        await flushPromises();
    });

    it('should initialize with default units when no preferences exist', async () => {
        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
        expect(Shopware.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('mm');
        expect(Shopware.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('kg');
    });

    it('should initialize with preferred units when they exist', async () => {
        const preferredUnits = {
            length: 'cm',
            weight: 'g',
        };

        Shopware.Service('userConfigService').search.mockResolvedValue({
            data: {
                'measurement.preferenceUnits': preferredUnits,
            },
        });

        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('cm');
        expect(wrapper.vm.previousWeightUnit).toBe('g');
        expect(Shopware.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('cm');
        expect(Shopware.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('g');
    });

    it('should initialize with default units when the preferences cannot be loaded', async () => {
        Shopware.Service('userConfigService').search.mockRejectedValue(new Error('Request failed'));

        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
        expect(Shopware.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('mm');
        expect(Shopware.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('kg');
    });

    it('should save preferences only when units have changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        await wrapper.vm.saveProduct();

        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should not save preferences when units have not changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'mm',
            previousWeightUnit: 'kg',
        });

        await wrapper.vm.saveProduct();

        expect(Shopware.Service('userConfigService').upsert).not.toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should handle errors when saving preferences', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        Shopware.Service('userConfigService').upsert.mockRejectedValue(new Error('Save failed'));

        await wrapper.vm.saveProduct();

        expect(Shopware.Service('userConfigService').upsert).toHaveBeenCalled();
        // Previous units should not be updated on error
        expect(wrapper.vm.previousLengthUnit).toBe('cm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should set isSaveSuccessful to true when no SEO promises exist', () => {
        wrapper.vm.loadProduct = jest.fn();

        wrapper.vm.updateSeoPromises = [];

        wrapper.vm.onSaveFinished('success');

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle success response correctly', async () => {
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        Shopware.Store.get('swProductDetail').setLoading = jest.fn();
        jest.spyOn(Shopware.Store.get('error'), 'resetApiErrors');
        wrapper.vm.loadProduct = jest.fn();

        Shopware.Utils.EventBus.emit = jest.fn();

        wrapper.vm.onSaveFinished('success');

        expect(Shopware.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith([
            'product',
            true,
        ]);

        await flushPromises();

        expect(Shopware.Utils.EventBus.emit).toHaveBeenCalledWith('sw-product-detail-save-finish');
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(Shopware.Store.get('error').resetApiErrors).toHaveBeenCalled();
        expect(Shopware.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith([
            'product',
            false,
        ]);
        expect(wrapper.vm.loadProduct).toHaveBeenCalled();
    });

    it.each([
        'success',
        'empty',
    ])('should discard the api errors of the previous save when the save finished with "%s"', async (response) => {
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.updateSeoPromises = [];

        Shopware.Store.get('error').addApiError({
            expression: 'product.1234.guaranteeMonths',
            error: {
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
            },
        });

        wrapper.vm.onSaveFinished(response);
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(Shopware.Store.get('error').api).toEqual({});
    });

    it('should keep the api errors when the save failed', async () => {
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.updateSeoPromises = [];

        Shopware.Store.get('error').addApiError({
            expression: 'product.1234.guaranteeMonths',
            error: {
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value greater than 24 months.',
            },
        });

        wrapper.vm.onSaveFinished({ response: { data: { errors: [{ detail: 'nope' }] } } });
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(Shopware.Store.get('error').api.product['1234'].guaranteeMonths).toBeDefined();
    });

    it('should handle duplicate product number error correctly', async () => {
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        wrapper.vm.isSaveSuccessful = false;
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();

        Shopware.Utils.EventBus.emit = jest.fn();

        const duplicateErrorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER',
                            meta: {
                                parameters: {
                                    number: 'SW-123',
                                },
                            },
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(duplicateErrorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-product.notification.notificationSaveErrorProductNoAlreadyExists',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle duplicate product number error when seo promises are empty', async () => {
        wrapper.vm.updateSeoPromises = [];
        wrapper.vm.isSaveSuccessful = false;
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();

        const duplicateErrorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER',
                            meta: {
                                parameters: {
                                    number: 'SW-123',
                                },
                            },
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(duplicateErrorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-product.notification.notificationSaveErrorProductNoAlreadyExists',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle generic error with detail correctly', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        const errorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            detail: 'Custom error message',
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(errorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'Custom error message',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle SEO promise rejection correctly', async () => {
        const rejectedPromise = Promise.reject(new Error('SEO error'));
        wrapper.vm.updateSeoPromises = [rejectedPromise];
        Shopware.Store.get('swProductDetail').setLoading = jest.fn();
        wrapper.vm.loadProduct = jest.fn();

        wrapper.vm.onSaveFinished('success');

        await flushPromises();

        expect(Shopware.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith([
            'product',
            false,
        ]);
        expect(wrapper.vm.loadProduct).toHaveBeenCalled();
    });

    it('should not validate fields when language is inherited', async () => {
        const spyValidationService = jest.spyOn(wrapper.vm.entityValidationService, 'validate');

        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.product.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.product.prices = [];
        wrapper.vm.product.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.product.getEntityName = () => 'product';
        Shopware.EntityDefinition.get = () => ({
            properties: {
                name: {
                    type: 'string',
                    flags: {
                        required: true,
                    },
                },
            },
        });

        Shopware.Store.get('context').api.language = {
            id: '1a2b3c',
            parentId: 'd4e5f6',
        };

        wrapper.vm.product.name = null;

        await wrapper.vm.onSave();

        expect(wrapper.vm.ignoreFieldsValidation).toContain('name');
        expect(spyValidationService).toHaveBeenCalledWith(
            wrapper.vm.product,
            expect.anything(),
            expect.arrayContaining(['name']),
        );
    });

    it('should validate fields when language is not inherited', async () => {
        const spyValidationService = jest.spyOn(wrapper.vm.entityValidationService, 'validate');

        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.product.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.product.prices = [];
        wrapper.vm.product.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.product.getEntityName = () => 'product';
        Shopware.EntityDefinition.get = () => ({
            properties: {
                name: {
                    type: 'string',
                    flags: {
                        required: true,
                    },
                },
            },
        });

        Shopware.Store.get('context').api.language = {
            id: '1a2b3c',
            parentId: null,
        };

        wrapper.vm.product.name = null;

        await wrapper.vm.onSave();

        expect(wrapper.vm.ignoreFieldsValidation).not.toContain('name');
        expect(spyValidationService).toHaveBeenCalledWith(wrapper.vm.product, expect.anything(), []);
    });

    it('should handle the purchase price if its not set', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
        expect(wrapper.vm.product._origin.purchasePrices).toEqual(wrapper.vm.product.purchasePrices);
    });

    it('should handle the purchase price if its null', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: null,
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
    });

    it('should handle the purchase price if its undefined', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: undefined,
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
    });

    it('should synchronize the default purchase price with the parent product origin', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            (id) => {
                if (id === 'parent-id') {
                    return Promise.resolve({
                        id: 'parent-id',
                        purchasePrices: undefined,
                    });
                }

                return Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                    purchasePrices: [{ currencyId: undefined, net: 42, gross: 50, linked: true }],
                });
            },
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.parentProduct.purchasePrices).toEqual([
            { currencyId: undefined, gross: 0, net: 0, linked: true },
        ]);
        expect(wrapper.vm.parentProduct._origin.purchasePrices).toEqual(wrapper.vm.parentProduct.purchasePrices);
    });

    it('should not overwrite purchase price for variant products with parentId when null', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: null,
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toBeNull();
    });

    it('should not overwrite purchase price for variant products with parentId when undefined', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: undefined,
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toBeNull();
    });

    it('should keep existing purchase price for variant products with their own values', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: [
                        {
                            currencyId: undefined,
                            net: 10,
                            gross: 12,
                            linked: true,
                        },
                    ],
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 5,
                            gross: 6,
                            linked: true,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toEqual([{ currencyId: undefined, net: 5, linked: true, gross: 6 }]);
    });

    it('should sync purchasePrices to null when price is inherited but purchasePrices is not', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 5,
                            gross: 6,
                            linked: true,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toBeNull();
    });

    it('should sync purchasePrices from parent when price is not inherited but purchasePrices is', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            (id) => {
                if (id === 'parent-id') {
                    return Promise.resolve({
                        id: 'parent-id',
                        price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                        purchasePrices: [{ currencyId: undefined, net: 42, gross: 50, linked: true }],
                    });
                }

                return Promise.resolve({
                    id: 'variant-id',
                    parentId: 'parent-id',
                    price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                    purchasePrices: null,
                });
            },
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.purchasePrices).toEqual([
            { currencyId: undefined, gross: 50, net: 42, linked: true },
        ]);
    });

    it('should ignore purchase price if its set', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 10,
                            gross: 19,
                            linked: false,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            productId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.product.id).toBe('test');
        expect(wrapper.vm.product.purchasePrices).toEqual([{ currencyId: undefined, net: 10, linked: false, gross: 19 }]);
    });

    it('should reset mode settings to default when creating a new product', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({}),
            null,
        );

        await flushPromises();

        expect(wrapper.vm.modeSettings).toEqual([
            'general_information',
            'prices',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
            'measurement',
            'selling_packaging',
            'properties',
            'essential_characteristics',
            'custom_fields',
        ]);
    });

    it('should load mode settings from cached user config when editing existing product', async () => {
        const mockSettings = {
            advancedMode: {
                label: 'sw-product.general.textAdvancedMode',
                enabled: true,
            },
            settings: [
                {
                    key: 'prices',
                    label: 'sw-product.detailBase.cardTitlePrices',
                    enabled: false,
                    name: 'general',
                },
            ],
        };

        Shopware.Service('userConfigService').search.mockImplementation((keys) => {
            if (keys.includes('mode.setting.advancedModeSettings')) {
                return Promise.resolve({
                    data: {
                        'mode.setting.advancedModeSettings': mockSettings,
                    },
                });
            }

            return Promise.resolve({ data: {} });
        });

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({}),
            null,
        );

        await flushPromises();

        await wrapper.setProps({ productId: '1234' });
        await flushPromises();

        // 'prices' should be missing from modeSettings
        expect(wrapper.vm.modeSettings).toEqual([
            'general_information',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
            'measurement',
            'selling_packaging',
            'properties',
            'essential_characteristics',
            'custom_fields',
        ]);
        expect(Shopware.Service('userConfigService').search).toHaveBeenCalledWith(['mode.setting.advancedModeSettings']);
    });

    it('should clear stale variant data when opening create page after viewing a variant product', async () => {
        await wrapper.unmount();

        const store = Shopware.Store.get('swProductDetail');
        store.product = {
            id: 'variant-123',
            parentId: 'parent-456',
            variation: [],
        };
        store.parentProduct = { id: 'parent-456', name: 'Parent Product' };

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({ variation: [] }),
            null,
        );

        await flushPromises();
        expect(store.parentProduct).toEqual({});
    });
});
