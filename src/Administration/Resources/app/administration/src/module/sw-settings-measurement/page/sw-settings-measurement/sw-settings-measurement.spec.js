/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const createWrapper = async (options = {}) => {
    const mockMeasurementSystem = {
        'core.measurementSystem.typeId': 'metric',
        'core.measurementSystem.lengthUnitId': 'mm',
        'core.measurementSystem.massUnitId': 'kg',
    };

    const mockDefaultUnits = new EntityCollection(
        '/measurement-display-unit',
        'measurement_display_unit',
        null,
        { isShopwareContext: true },
        [
            { id: 'mm', type: 'length', measurementSystemId: 'metric' },
            { id: 'kg', type: 'mass', measurementSystemId: 'metric' },
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
        }),
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

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should load measurement system data on creation', async () => {
        await wrapper.vm.createdComponent();

        expect(wrapper.vm.measurementSystem).toEqual({
            typeId: 'metric',
            lengthUnitId: 'mm',
            massUnitId: 'kg',
        });
        expect(wrapper.vm.defaultDisplayUnits).toHaveLength(2);
        expect(wrapper.vm.defaultDisplayUnits[0].id).toBe('mm');
        expect(wrapper.vm.defaultDisplayUnits[1].id).toBe('kg');
    });

    it('should save measurement system settings successfully', async () => {
        await wrapper.setData({
            measurementSystem: {
                typeId: 'imperial',
                lengthUnitId: 'in',
                massUnitId: 'lb',
            },
        });

        wrapper.vm.systemConfigApiService.saveValues.mockResolvedValue();
        wrapper.vm.createNotificationSuccess = jest.fn();

        const saveButton = wrapper.find('.sw-settings-measurement__save-action');
        await saveButton.trigger('click');

        expect(wrapper.vm.systemConfigApiService.saveValues).toHaveBeenCalledWith({
            'core.measurementSystem.typeId': 'imperial',
            'core.measurementSystem.lengthUnitId': 'in',
            'core.measurementSystem.massUnitId': 'lb',
        });
        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalledWith({
            title: 'global.default.success',
            message: 'sw-settings-measurement.notification.saveMeasurementSuccess',
        });
    });

    it('should handle save error gracefully', async () => {
        await wrapper.setData({
            measurementSystem: {
                typeId: 'metric',
                lengthUnitId: 'mm',
                massUnitId: 'kg',
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
        const mockImperialUnits = new EntityCollection(
            '/measurement-display-unit',
            'measurement_display_unit',
            null,
            { isShopwareContext: true },
            [
                { id: 'in', type: 'length', measurementSystemId: 'imperial' },
                { id: 'lb', type: 'mass', measurementSystemId: 'imperial' },
            ],
            2,
            null,
        );

        await wrapper.setData({
            defaultDisplayUnits: mockImperialUnits,
            measurementSystem: {
                typeId: 'imperial',
            },
        });

        wrapper.vm.onChangeMeasurementSystem();

        expect(wrapper.vm.measurementSystem.lengthUnitId).toBe('in');
        expect(wrapper.vm.measurementSystem.massUnitId).toBe('lb');
    });
});
