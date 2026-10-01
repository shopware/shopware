import { mount } from '@vue/test-utils';
import IdCollection from 'test/_helper_/id.collection';

/**
 * @sw-package checkout
 */

let repositoryFactoryMock;

async function createWrapper(privileges = [], props = {}, serviceOverrides = {}) {
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
                    ...serviceOverrides,
                },
                stubs: {
                    'sw-page': {
                        template:
                            '<div><slot name="content"></slot><slot name="smart-bar-actions"></slot><slot name="sidebar"></slot></div>',
                    },
                    'sw-button-process': true,
                    'sw-sidebar': true,
                    'sw-sidebar-media-item': {
                        template: '<div />',
                        methods: {
                            getList: () => {},
                        },
                    },
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
        const wrapper = await createWrapper(['shipping.editor']);
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

    it('should initialize shipping price with quantityStart=0 after creating component', async () => {
        const wrapper = await createWrapper([]);
        expect(wrapper.vm.shippingMethod.quantityStart).toBe(0);
    });

    describe('saving shipping price matrices', () => {
        const repositoryFactory = Shopware.Service('repositoryFactory');
        const { clientMock, responses } = global.repositoryFactoryMock;
        let wrapper;
        let ids;

        beforeEach(() => {
            ids = new IdCollection();
            clientMock.resetHistory();
        });

        afterEach(() => {
            wrapper?.unmount();
        });

        async function loadShippingMethod() {
            const shippingId = ids.get('shipping');
            const priceId = ids.get('original-price');
            const apiResourcePath = Shopware.Context.api.apiResourcePath ?? '';
            const relationships = {};
            const extensionRelationships = {};

            Object.entries(Shopware.EntityDefinition.get('shipping_method').getToManyAssociations()).forEach(
                ([name, field]) => {
                    const associations = field.flags.extension ? extensionRelationships : relationships;
                    associations[name] = {
                        data: [],
                        links: { related: `${apiResourcePath}/shipping-method/${shippingId}/${name}` },
                    };
                },
            );
            relationships.prices.data = [{ id: priceId, type: 'shipping_method_price' }];
            relationships.extensions = { data: { id: shippingId, type: 'extension' } };

            responses.addResponse({
                method: 'POST',
                url: '/search/shipping-method',
                response: {
                    data: [
                        {
                            id: shippingId,
                            type: 'shipping_method',
                            attributes: {
                                name: 'Express',
                                technicalName: 'express',
                                active: true,
                                extensions: {},
                            },
                            relationships,
                        },
                    ],
                    included: [
                        {
                            id: priceId,
                            type: 'shipping_method_price',
                            attributes: {
                                shippingMethodId: shippingId,
                                calculation: 1,
                                quantityStart: 0,
                                quantityEnd: null,
                                ruleId: null,
                                currencyPrice: [
                                    { currencyId: ids.get('currency'), gross: 10, net: 10, linked: false },
                                ],
                                extensions: {},
                            },
                            relationships: {},
                        },
                        {
                            id: shippingId,
                            type: 'extension',
                            attributes: {},
                            relationships: extensionRelationships,
                        },
                    ],
                },
            });
            responses.addResponse({
                method: 'POST',
                url: '/search/currency',
                response: { data: [] },
            });
            responses.addResponse({
                method: 'POST',
                url: '_action/sync',
                response: {},
            });
            responses.addResponse({
                method: 'DELETE',
                url: `/shipping-method/${shippingId}/prices/${priceId}`,
                response: {},
            });
            responses.addResponse({
                method: 'PATCH',
                url: `/shipping-method/${shippingId}`,
                response: {},
            });

            wrapper = await createWrapper(['shipping.editor'], { shippingMethodId: shippingId }, { repositoryFactory });
            await flushPromises();
            clientMock.resetHistory();
        }

        it.each([45, 0])(
            'should replace the last persisted matrix with cart-value prices costing %s in one request',
            async (cost) => {
                await loadShippingMethod();

                const shippingMethod = wrapper.vm.shippingMethod;
                const replacement = repositoryFactory.create('shipping_method_price').create();
                Object.assign(replacement, {
                    shippingMethodId: shippingMethod.id,
                    calculation: 2,
                    quantityStart: 0,
                    currencyPrice: [{ currencyId: ids.get('currency'), gross: cost, net: cost, linked: false }],
                });
                shippingMethod.prices.remove(ids.get('original-price'));
                shippingMethod.prices.add(replacement);

                await wrapper.vm.onSave();
                await flushPromises();

                expect(clientMock.history.delete.map((request) => request.url)).toEqual([]);
                const saveRequests = clientMock.history.post.filter((request) => request.url === '_action/sync');
                expect(saveRequests).toHaveLength(1);
                expect(JSON.parse(saveRequests[0].data)).toEqual([
                    {
                        entity: 'shipping_method_price',
                        action: 'delete',
                        payload: [{ id: ids.get('original-price') }],
                    },
                    {
                        key: 'write',
                        entity: 'shipping_method',
                        action: 'upsert',
                        payload: [
                            {
                                id: shippingMethod.id,
                                prices: [
                                    {
                                        id: replacement.id,
                                        shippingMethodId: shippingMethod.id,
                                        calculation: 2,
                                        quantityStart: 0,
                                        currencyPrice: [
                                            { currencyId: ids.get('currency'), gross: cost, net: cost, linked: false },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ]);
                expect(wrapper.vm.isSaveSuccessful).toBe(true);
            },
        );

        it('should show the validation error when saving an active shipping method without any prices', async () => {
            await loadShippingMethod();
            const detail = `The shipping method ${ids.get('shipping')} is active and must therefore have at least one price with currency values.`;
            responses.addResponse({
                method: 'POST',
                url: '_action/sync',
                status: 400,
                response: {
                    errors: [
                        {
                            code: 'active_shipping_method_without_price',
                            status: '400',
                            detail,
                            source: { pointer: '/prices' },
                        },
                    ],
                },
            });
            const notificationSpy = jest.spyOn(wrapper.vm, 'createNotificationError');
            const warningSpy = jest.spyOn(console, 'warn').mockImplementation();
            wrapper.vm.shippingMethod.prices.remove(ids.get('original-price'));

            await expect(wrapper.vm.onSave()).rejects.toMatchObject({
                response: {
                    status: 400,
                    data: { errors: [expect.objectContaining({ code: 'active_shipping_method_without_price' })] },
                },
            });

            expect(clientMock.history.delete.map((request) => request.url)).toEqual([]);
            const saveRequests = clientMock.history.post.filter((request) => request.url === '_action/sync');
            expect(saveRequests).toHaveLength(1);
            expect(JSON.parse(saveRequests[0].data)).toEqual([
                {
                    entity: 'shipping_method_price',
                    action: 'delete',
                    payload: [{ id: ids.get('original-price') }],
                },
            ]);
            expect(notificationSpy).toHaveBeenCalledWith({
                title: 'global.default.error',
                message: expect.stringContaining(detail),
            });
            expect(wrapper.vm.isSaveSuccessful).toBe(false);
            expect(wrapper.vm.isProcessLoading).toBe(false);
            warningSpy.mockRestore();
        });
    });
});
