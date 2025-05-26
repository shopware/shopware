/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-sales-channel-measurement', { sync: true }), {
        props: {
            salesChannel: {
                measurementSystemId: '1',
                measurementUnits: {
                    length: '2',
                    weight: '3'
                }
            },
        },
        global: {
            stubs: {
                'sw-container': true,
                'sw-entity-single-select': true,
                'sw-highlight-text': true,
            },
            mocks: {
                $t: (key) => key,
            },
        },
    });

    return wrapper;
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-measurement', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });


    it('should initialize with correct default values', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.defaultMeasurementSystem).toEqual({
            measurementSystemId: '1',
            measurementUnits: {
                length: '2',
                weight: '3'
            }
        });
    });

    it('should use default labels when custom labels are not provided', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.unitSystemLabel).toBe('sw-sales-channel.detail.measurementSystem.labelUnitSystem');
        expect(wrapper.vm.dimensionUnitLabel).toBe('sw-sales-channel.detail.measurementSystem.labelLengthUnit');
        expect(wrapper.vm.weightUnitLabel).toBe('sw-sales-channel.detail.measurementSystem.labelWeightUnit');
    });

    it('should format unit label correctly', async () => {
        const wrapper = await createWrapper();
        const unit = {
            name: 'Centimeter',
            shortName: 'cm',
            translated: {
                name: 'Centimeter'
            }
        };

        const formattedLabel = wrapper.vm.formatUnitLabel(unit);
        expect(formattedLabel).toBe('Centimeter (cm)');
    });

    it('should handle empty unit in formatUnitLabel', async () => {
        const wrapper = await createWrapper();
        const formattedLabel = wrapper.vm.formatUnitLabel(null);
        expect(formattedLabel).toBe('');
    });

    it('creates criteria with units association and filters default units', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.measurementSystemCriteria;

        expect(criteria.associations).toContainEqual({
            association: 'units',
            criteria: expect.objectContaining({
                filters: [{
                    field: 'default',
                    type: 'equals',
                    value: true
                }]
            })
        });
    });

    it('should create correct criteria for length units', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.lengthUnitCriteria;

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'type',
            value: 'length'
        });

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'measurementSystem.id',
            value: '1'
        });
    });

    it('should create correct criteria for mass units', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.weightUnitCriteria;

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'type',
            value: 'weight'
        });

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'measurementSystem.id',
            value: '1'
        });
    });

    it('should emit "measurement-system-change" when measurement system changes', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onMeasurementSystemChange('system2');
        expect(wrapper.emitted('measurement-system-change')).toBeTruthy();
        expect(wrapper.emitted('measurement-system-change')[0][0]).toBe('system2');
    });

    it('should update measurement units when measurement system changes', async () => {
        const wrapper = await createWrapper();
        
        const mockMeasurementSystem = {
            id: 'system2',
            units: [
                { id: 'unit1', type: 'length' },
                { id: 'unit2', type: 'weight' }
            ]
        };
        
        // Mock the first method
        mockMeasurementSystem.units.first = function() { return this[0]; };
        
        wrapper.vm.$refs.measurementSystemSelect = {
            results: new Map([['system2', mockMeasurementSystem]])
        };
        
        await wrapper.vm.onMeasurementSystemChange('system2');
        
        expect(wrapper.vm.salesChannel.measurementUnits).toEqual({
            length: 'unit1',
            weight: 'unit2'
        });
    });

    it('should restore default measurement units when returning to default system', async () => {
        const wrapper = await createWrapper();
        const originalUnits = { ...wrapper.vm.salesChannel.measurementUnits };
        
        // Change to a different system first
        wrapper.vm.salesChannel.measurementUnits = { length: 'new1', weight: 'new2' };
        
        // Then change back to the default system
        await wrapper.vm.onMeasurementSystemChange('1');
        
        expect(wrapper.vm.salesChannel.measurementUnits).toEqual(originalUnits);
    });

    it('should correctly get and set lengthUnitId via computed property', async () => {
        const wrapper = await createWrapper();
        
        // Test getter
        expect(wrapper.vm.lengthUnitId).toBe('2');
        
        // Test setter
        wrapper.vm.lengthUnitId = 'newLength';
        expect(wrapper.vm.salesChannel.measurementUnits.length).toBe('newLength');
    });

    it('should correctly get and set weightUnitId via computed property', async () => {
        const wrapper = await createWrapper();
        
        // Test getter
        expect(wrapper.vm.weightUnitId).toBe('3');
        
        // Test setter
        wrapper.vm.weightUnitId = 'newWeight';
        expect(wrapper.vm.salesChannel.measurementUnits.weight).toBe('newWeight');
    });
});
