/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const createWrapper = async (options = {}, privileges = []) => {
    const mockMeasurementSystem = {
        'core.measurementUnits.system': 'metric',
        'core.measurementUnits.length': 'mm',
        'core.measurementUnits.weight': 'kg',
    };

    const mockDefaultUnits = new EntityCollection(
        '/measurement-system',
        'measurement_system',
        null,
        { isShopwareContext: true },
        [
            {
                id: 'metric',
                name: 'Metric system',
                technicalName: 'metric',
                units: new EntityCollection(
                    '/measurement-display-unit',
                    'measurement_display_unit',
                    null,
                    {},
                    [
                        { id: 'mm', type: 'length', measurementSystemId: 'metric', shortName: 'mm', default: true },
                        { id: 'kg', type: 'weight', measurementSystemId: 'metric', shortName: 'kg', default: true },
                        { id: 'cm', type: 'length', measurementSystemId: 'metric', shortName: 'cm', default: false },
                        { id: 'g', type: 'weight', measurementSystemId: 'metric', shortName: 'g', default: false },
                    ],
                    2,
                    null,
                ),
                getEntityName: () => 'measurement_display_unit',
            },
            {
                id: 'imperial',
                name: 'Imperial system',
                technicalName: 'imperial',
                units: new EntityCollection(
                    '/measurement-display-unit',
                    'measurement_display_unit',
                    null,
                    {},
                    [
                        { id: 'in', type: 'length', measurementSystemId: 'imperial', shortName: 'in', default: true },
                        { id: 'lb', type: 'weight', measurementSystemId: 'imperial', shortName: 'lb', default: true },
                    ],
                    2,
                    null,
                ),
                getEntityName: () => 'measurement_display_unit',
            },
        ],
        2,
        null,
    );

    const systemConfigApiService = {
        getValues: jest.fn().mockResolvedValue(mockMeasurementSystem),
        saveValues: jest.fn(),
    };

    const repositoryFactory = {
        create: () => ({
            search: jest.fn().mockResolvedValue(mockDefaultUnits),
            create: jest.fn().mockResolvedValue({
                id: 'new-id',
            }),
        }),
    };

    const acl = {
        can: (privilege) => {
            if (!privilege) {
                return true;
            }

            return privileges.includes(privilege);
        },
    };

    return mount(
        await wrapTestComponent('sw-settings-measurement', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                            <div class="sw-page">
                                <slot name="smart-bar-header"></slot>
                                <slot name="language-switch"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="content"></slot>
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-card-view': {
                        template: `
                            <div class="sw-card-view">
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-language-switch': true,
                    'sw-settings-measurement-default-units': true,
                },
                provide: {
                    repositoryFactory,
                    systemConfigApiService,
                    acl,
                },
                mocks: {
                    $createTitle: () => 'Test Title',
                },
                plugins: [],
            },
            ...options,
        },
    );
};

describe('src/module/sw-settings-measurement/page/sw-settings-measurement', () => {
    let wrapper;
    const addApiError = jest.fn();
    const resetApiErrors = jest.fn();

    beforeEach(async () => {
        wrapper = await createWrapper({}, ['measurement.creator']);

        Shopware.Store.unregister('error');
        Shopware.Store.register({
            id: 'error',
            actions: {
                addApiError,
                resetApiErrors,
            },
        });
    });

    it('should be a Vue component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should load measurement system data on creation', async () => {
        await wrapper.vm.createdComponent();

        expect(wrapper.vm.measurementUnits).toEqual({
            system: 'metric',
            length: 'mm',
            weight: 'kg',
        });
        expect(wrapper.vm.defaultDisplayUnits).toHaveLength(2);
        expect(wrapper.vm.defaultDisplayUnits[0].id).toBe('mm');
        expect(wrapper.vm.defaultDisplayUnits[1].id).toBe('kg');
    });

    it('should not save when measurement units are missing', async () => {
        await wrapper.setData({
            measurementUnits: {
                system: null,
                length: null,
                weight: null,
            },
        });

        await flushPromises();

        const saveButton = wrapper.find('.sw-settings-measurement__save-action');
        await saveButton.trigger('click');

        expect(addApiError).toHaveBeenCalledTimes(3);
        expect(wrapper.vm.systemConfigApiService.saveValues).not.toHaveBeenCalled();
    });

    it('should save measurement system settings successfully', async () => {
        await wrapper.setData({
            measurementUnits: {
                system: 'imperial',
                length: 'in',
                weight: 'lb',
            },
        });

        wrapper.vm.systemConfigApiService.saveValues.mockResolvedValue();
        wrapper.vm.createNotificationSuccess = jest.fn();

        const saveButton = wrapper.find('.sw-settings-measurement__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.systemConfigApiService.saveValues).toHaveBeenCalledWith({
            'core.measurementUnits.system': 'imperial',
            'core.measurementUnits.length': 'in',
            'core.measurementUnits.weight': 'lb',
        });
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            title: 'global.default.success',
            message: 'sw-settings-measurement.notification.saveMeasurementSuccess',
        });
    });

    it('should handle save error gracefully', async () => {
        await wrapper.setData({
            measurementUnits: {
                system: 'metric',
                length: 'mm',
                weight: 'kg',
            },
        });

        wrapper.vm.systemConfigApiService.saveValues.mockRejectedValue(new Error('Save failed'));
        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-settings-measurement__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'Save failed',
        });
    });

    it('should update measurement units when system changes', async () => {
        await wrapper.vm.onChangeMeasurementSystem('imperial');

        expect(resetApiErrors).toHaveBeenCalled();
        expect(wrapper.vm.measurementUnits).toEqual({
            system: 'imperial',
            length: 'in',
            weight: 'lb',
        });
    });

    it('should update defaultDisplayUnits and selected units when measurement system units are changed and back', async () => {
        await wrapper.setData({
            defaultDisplayUnits: new EntityCollection(
                '/measurement-display-unit',
                'measurement_display_unit',
                null,
                { isShopwareContext: true },
                [
                    { id: 'cm', type: 'length', measurementSystemId: 'metric', shortName: 'cm', default: false },
                    { id: 'g', type: 'weight', measurementSystemId: 'metric', shortName: 'g', default: false },
                ],
                2,
                null,
            ),
        });

        expect(wrapper.vm.defaultDisplayUnits).toBeInstanceOf(EntityCollection);
        expect(wrapper.vm.defaultDisplayUnits.getIds()).toEqual(
            expect.arrayContaining([
                'cm',
                'g',
            ]),
        );

        await wrapper.vm.onChangeMeasurementSystem('imperial');

        expect(wrapper.vm.measurementUnits).toEqual({
            system: 'imperial',
            length: 'in',
            weight: 'lb',
        });

        await wrapper.vm.onChangeMeasurementSystem('metric');

        expect(wrapper.vm.measurementUnits).toEqual({
            system: 'metric',
            length: 'cm',
            weight: 'g',
        });
    });
});
