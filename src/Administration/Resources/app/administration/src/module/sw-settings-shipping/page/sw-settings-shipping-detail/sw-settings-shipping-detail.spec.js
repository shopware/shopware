import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

let repositoryFactoryMock;

async function createWrapper(privileges = [], props = {}) {
    const shippingMethod = {};
    shippingMethod.technicalName = 'shipping_standard';
    shippingMethod.getEntityName = () => 'shipping_method';
    shippingMethod.isNew = () => false;
    shippingMethod.prices = {
        add: () => {},
        forEach: () => [],
    };
    repositoryFactoryMock = {
        create: () => {
            return shippingMethod;
        },
        search: () => Promise.resolve([]),
        get: () => Promise.resolve(shippingMethod),
        save: () => Promise.resolve(),
    };

    return mount(
        await wrapTestComponent('sw-settings-shipping-detail', {
            sync: true,
        }),
        {
            props,
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    ruleConditionDataProviderService: {
                        getRestrictedRules: () => Promise.resolve([]),
                    },
                    repositoryFactory: {
                        create: () => repositoryFactoryMock,
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    feature: {
                        isActive: () => true,
                    },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot></div>',
                    },
                    'sw-button-process': true,
                    'sw-sidebar': true,
                    'sw-sidebar-media-item': true,
                    'sw-card-view': true,
                    'sw-container': true,
                    'sw-text-field': {
                        props: ['disabled'],
                        template: '<input class="sw-field" :disabled="disabled" />',
                    },
                    'mt-number-field': {
                        props: ['disabled'],
                        template: '<input class="sw-field" :disabled="disabled" />',
                    },
                    'mt-textarea': {
                        props: ['disabled'],
                        template: '<input class="sw-field sw-textarea-field" :disabled="disabled" />',
                    },
                    'sw-upload-listener': true,
                    'sw-media-upload-v2': true,
                    'sw-entity-single-select': true,
                    'sw-entity-tag-select': true,
                    'sw-select-rule-create': true,
                    'sw-settings-shipping-price-matrices': true,
                    'sw-settings-shipping-tax-cost': true,
                    'sw-language-info': true,
                    'sw-skeleton': true,
                    'sw-language-switch': true,
                    'sw-custom-field-set-renderer': true,
                    'sw-context-menu-item': true,
                },
            },
        },
    );
}

describe('module/sw-settings-shipping/page/sw-settings-shipping-detail', () => {
    it('should have all fields disabled', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            isProcessLoading: false,
        });

        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBe('true');

        const swFields = wrapper.findAll('.sw-field');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.forEach((swField) => {
            expect(swField.attributes().disabled).toBeDefined();
        });

        const textareaField = wrapper.find('.sw-field.sw-textarea-field');
        expect(textareaField.attributes().disabled).toBeDefined();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');
        expect(mediaUpload.attributes().disabled).toBe('true');

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBe('true');

        const entityTagSelect = wrapper.find('sw-entity-tag-select-stub');
        expect(entityTagSelect.attributes().disabled).toBe('true');

        const settingsShippingPriceMatrices = wrapper.find('sw-settings-shipping-price-matrices-stub');
        expect(settingsShippingPriceMatrices.attributes().disabled).toBe('true');

        const settingsShippingTax = wrapper.find('sw-settings-shipping-tax-cost-stub');
        expect(settingsShippingTax.attributes().disabled).toBe('true');
    });

    it('should have all fields enabled', async () => {
        const wrapper = await createWrapper([
            'shipping.editor',
        ]);
        await wrapper.setData({
            isProcessLoading: false,
        });

        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-shipping-method-detail__save-action');
        expect(saveButton.attributes().disabled).toBeUndefined();

        const swFields = wrapper.findAll('.sw-field');
        expect(swFields.length).toBeGreaterThan(0);

        swFields.forEach((swField) => {
            expect(swField.attributes().disabled).toBeUndefined();
        });

        const textareaField = wrapper.find('.sw-field.sw-textarea-field');
        expect(textareaField.attributes().disabled).toBeUndefined();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');
        expect(mediaUpload.attributes().disabled).toBeUndefined();

        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.attributes().disabled).toBeUndefined();

        const entityTagSelect = wrapper.find('sw-entity-tag-select-stub');
        expect(entityTagSelect.attributes().disabled).toBeUndefined();

        const settingsShippingPriceMatrices = wrapper.find('sw-settings-shipping-price-matrices-stub');
        expect(settingsShippingPriceMatrices.attributes().disabled).toBeUndefined();

        const settingsShippingTax = wrapper.find('sw-settings-shipping-tax-cost-stub');
        expect(settingsShippingTax.attributes().disabled).toBeUndefined();
    });

    it('should add conditions association', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });

    it('should load customFieldSet on loadEntityData', async () => {
        const wrapper = await createWrapper([], { shippingMethodId: 'a1b2c3' });
        const spyGetMethod = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');
        const spyLoadCustomFieldSets = jest.spyOn(wrapper.vm, 'loadCustomFieldSets');

        wrapper.vm.loadEntityData();

        await flushPromises();
        expect(spyGetMethod).toHaveBeenCalled();
        expect(spyLoadCustomFieldSets).toHaveBeenCalled();
    });

    it('should create notification on save error', async () => {
        const wrapper = await createWrapper();
        const spy = jest.spyOn(wrapper.vm, 'createNotificationError');
        const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
        const error = new Error('error');

        wrapper.vm.shippingMethodRepository.save = () => Promise.reject(error);
        wrapper.vm.shippingMethod.prices = [];

        await expect(wrapper.vm.onSave()).rejects.toBe(error);
        expect(spy).toHaveBeenCalled();
        expect(warningSpy).toHaveBeenCalled();
        expect(wrapper.vm.isProcessLoading).toBe(false);
    });

    it('should not load without entity id', async () => {
        const wrapper = await createWrapper();
        const spy = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');

        await flushPromises();
        wrapper.vm.loadEntityData();
        expect(spy).not.toHaveBeenCalled();
    });

    it('should load with entity id', async () => {
        const wrapper = await createWrapper([], { shippingMethodId: 'a1b2c3' });
        const spy = jest.spyOn(wrapper.vm.shippingMethodRepository, 'get');

        await flushPromises();
        wrapper.vm.loadEntityData();
        expect(spy).toHaveBeenCalled();
    });

    describe('recalculateLinkedPrices', () => {
        let calculatePrices;

        beforeEach(() => {
            calculatePrices = jest.fn().mockResolvedValue({});
            Shopware.Application.getContainer = () => ({
                apiService: { getByName: () => ({ calculatePrices }) },
            });
        });

        const setupShippingMethod = (wrapper, { taxType = 'fixed', taxId = 'taxId' } = {}) => {
            const shippingMethod = wrapper.vm.shippingMethod;

            shippingMethod.taxType = taxType;
            shippingMethod.taxId = taxId;
            shippingMethod.prices = [
                {
                    id: 'visiblePrice',
                    currencyPrice: [
                        { currencyId: 'euro', gross: 30, net: 0, linked: true },
                        { currencyId: 'dollar', gross: 60, net: 0, linked: false },
                    ],
                },
                {
                    // a tier the data grid does not render, so it never mounts an sw-price-field
                    id: 'hiddenPrice',
                    currencyPrice: [{ currencyId: 'euro', gross: 50, net: 0, linked: true }],
                },
            ];
            shippingMethod.prices.remove = () => {};

            return shippingMethod;
        };

        it('should recalculate every linked price, including the ones that are not rendered', async () => {
            const wrapper = await createWrapper();
            const shippingMethod = setupShippingMethod(wrapper);

            calculatePrices.mockResolvedValue({
                visiblePrice: { euro: { calculatedTaxes: [{ tax: 1.9626168224299 }] } },
                hiddenPrice: { euro: { calculatedTaxes: [{ tax: 3.2710280373832 }] } },
            });

            await wrapper.vm.recalculateLinkedPrices();

            // only linked prices are sent, in a single request
            expect(calculatePrices).toHaveBeenCalledWith('taxId', {
                visiblePrice: [{ currencyId: 'euro', price: 30, output: 'gross' }],
                hiddenPrice: [{ currencyId: 'euro', price: 50, output: 'gross' }],
            });

            expect(shippingMethod.prices[0].currencyPrice[0].net).toBe(28.03738317757);
            expect(shippingMethod.prices[1].currencyPrice[0].net).toBe(46.728971962617);

            // the unlinked price is untouched
            expect(shippingMethod.prices[0].currencyPrice[1].net).toBe(0);
        });

        it.each([
            ['auto'],
            ['highest'],
        ])('should not recalculate for the "%s" tax type', async (taxType) => {
            const wrapper = await createWrapper();
            setupShippingMethod(wrapper, { taxType });

            await wrapper.vm.recalculateLinkedPrices();

            expect(calculatePrices).not.toHaveBeenCalled();
        });

        it('should keep the entered prices saveable when the calculation fails', async () => {
            const wrapper = await createWrapper();
            const shippingMethod = setupShippingMethod(wrapper);

            calculatePrices.mockRejectedValue(new Error('calculation unavailable'));
            const consoleWarn = jest.spyOn(console, 'warn').mockImplementation(() => {});

            await expect(wrapper.vm.recalculateLinkedPrices()).resolves.toBeUndefined();

            expect(consoleWarn).toHaveBeenCalled();
            expect(shippingMethod.prices[0].currencyPrice[0].gross).toBe(30);

            consoleWarn.mockRestore();
        });

        it('should recalculate linked prices before saving', async () => {
            const wrapper = await createWrapper();
            setupShippingMethod(wrapper);

            wrapper.vm.$.refs.mediaSidebarItem = { getList: () => {} };

            const calls = [];
            jest.spyOn(wrapper.vm, 'recalculateLinkedPrices').mockImplementation(() => {
                calls.push('recalculate');
                return Promise.resolve();
            });
            jest.spyOn(wrapper.vm.shippingMethodRepository, 'save').mockImplementation(() => {
                calls.push('save');
                return Promise.resolve();
            });

            await wrapper.vm.onSave();

            expect(calls).toEqual([
                'recalculate',
                'save',
            ]);
        });
    });

    it('should initialize shipping price with quantityStart=0 after creating component', async () => {
        const wrapper = await createWrapper([]);
        expect(wrapper.vm.shippingMethod.quantityStart).toBe(0);
    });
});
