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
    ) {
        return mount(await wrapTestComponent('sw-product-detail', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: 'sw.product.detail.base',
                        params: {
                            id: productId,
                        },
                    },
                    $router: {
                        push: jest.fn(),
                    },
                },
                provide: {
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
                            get: getFunction,
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
                    entityValidationService: {
                        validate: (entity, customValidator) => {
                            let errors = [];
                            if (customValidator) {
                                errors = customValidator(errors, entity);
                            }

                            return errors.length < 1;
                        },
                    },
                    userConfigService: {
                        search: () => Promise.resolve({ data: {} }),
                        upsert: () => Promise.resolve(),
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
                        template: '<div class="sw-tabs"><slot /></div>',
                    },
                    'sw-tabs-item': {
                        template: '<div class="sw-tabs-item"><slot /></div>',
                        props: [
                            'route',
                            'title',
                        ],
                    },
                    'sw-inheritance-warning': true,
                    'router-link': true,
                    'sw-product-detail': await wrapTestComponent('sw-product-detail'),
                    'sw-extension-component-section': true,
                    'sw-product-clone-modal': true,
                },
                propsData: {
                    productId,
                },
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

    it('should show item tabs when advanced mode deactivate', async () => {
        wrapper.vm.userModeSettingsRepository.save = jest.fn(() => Promise.resolve());
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
        await wrapper.setProps({
            productId: '1234',
        });

        const contextButton = wrapper.find('.sw-product-settings-mode');
        expect(contextButton.exists()).toBeFalsy();

        const visibleTabItem = [
            '.sw-product-detail__tab-seo',
            '.sw-product-detail__tab-cross-selling',
            '.sw-product-detail__tab-reviews',
        ];

        const invisibleTabItem = [
            '.sw-product-detail__tab-variants',
            '.sw-product-detail__tab-layout',
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
        wrapper.vm.currencyRepository.search = jest.fn(() => {
            return Promise.resolve([
                {
                    id: '123',
                    name: 'EUR',
                },
            ]);
        });

        await wrapper.vm.loadCurrencies();
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

        wrapper = await createWrapper(() => {
            return Promise.resolve([
                {
                    id: '98432def39fc4624b33213a56b8c944d',
                    name: 'Headless',
                },
            ]);
        });

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
        wrapper.vm.userConfigService.search = jest.fn(() =>
            Promise.resolve({
                data: {},
            }),
        );

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

        wrapper.vm.userConfigService.search = jest.fn(() =>
            Promise.resolve({
                data: {
                    'measurement.preferenceUnits': preferredUnits,
                },
            }),
        );

        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('cm');
        expect(wrapper.vm.previousWeightUnit).toBe('g');
        expect(Shopware.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('cm');
        expect(Shopware.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('g');
    });

    it('should save preferences only when units have changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        await wrapper.vm.saveProduct();

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should not save preferences when units have not changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'mm',
            previousWeightUnit: 'kg',
        });

        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        await wrapper.vm.saveProduct();

        expect(wrapper.vm.userConfigService.upsert).not.toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should handle errors when saving preferences', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.reject(new Error('Save failed')));

        await wrapper.vm.saveProduct();

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalled();
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
        Shopware.Store.get('error').resetApiErrors = jest.fn();
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
        expect(Shopware.Store.get('error').resetApiErrors).not.toHaveBeenCalled();
        expect(Shopware.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith([
            'product',
            false,
        ]);
        expect(wrapper.vm.loadProduct).toHaveBeenCalled();
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

    it('should load mode settings from user config when editing existing product', async () => {
        // Mock user config with 'prices' disabled (enabled: false)
        const mockSettings = {
            first: () => ({
                value: {
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
                },
            }),
            total: 1,
        };

        wrapper = await createWrapper(
            (criteria) => {
                const isUserConfigSearch = criteria.filters.some(
                    (f) => f.field === 'key' && f.value === 'mode.setting.advancedModeSettings',
                );
                if (isUserConfigSearch) {
                    return Promise.resolve(mockSettings);
                }
                return Promise.resolve([]);
            },
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
