/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from '../../../../core/data/entity-collection.data';

async function createWrapper(repositoryMock, measurementUnits = {}, privileges = []) {
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
                        {
                            id: 'mm',
                            type: 'length',
                            measurementSystemId: 'metric',
                            shortName: 'mm',
                            name: 'Milimeter',
                            default: true,
                        },
                        {
                            id: 'kg',
                            type: 'weight',
                            measurementSystemId: 'metric',
                            shortName: 'kg',
                            name: 'Kilogram',
                            default: true,
                        },
                    ],
                    2,
                    null,
                ),
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
                        {
                            id: 'in',
                            type: 'length',
                            measurementSystemId: 'imperial',
                            shortName: 'in',
                            default: true,
                            name: 'Inch',
                        },
                        {
                            id: 'ft',
                            type: 'length',
                            measurementSystemId: 'imperial',
                            shortName: 'ft',
                            default: false,
                            name: 'Foot',
                        },
                        {
                            id: 'lb',
                            type: 'weight',
                            measurementSystemId: 'imperial',
                            shortName: 'lb',
                            default: true,
                            name: 'Pound',
                        },
                        {
                            id: 'oz',
                            type: 'weight',
                            measurementSystemId: 'imperial',
                            shortName: 'oz',
                            default: false,
                            name: 'Ounce',
                        },
                    ],
                    4,
                    null,
                ),
            },
        ],
        2,
        null,
    );

    const repositoryFactory = {
        create: () =>
            repositoryMock || {
                search: jest.fn().mockResolvedValue(mockDefaultUnits),
                get: jest.fn().mockResolvedValue(),
            },
    };

    const acl = {
        can: (privilege) => {
            if (!privilege) {
                return true;
            }

            return privileges.includes(privilege);
        },
    };

    return mount(await wrapTestComponent('sw-sales-channel-measurement', { sync: true }), {
        props: {
            salesChannel: {
                measurementUnits: {
                    system: 'metric',
                    units: {
                        length: 'mm',
                        weight: 'kg',
                    },
                    ...measurementUnits,
                },
            },
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-field-error': true,
                'sw-loader': true,
                'sw-product-variant-info': true,
                'mt-select': {
                    template: `
                        <select
                            class="mt-select__input"
                            :value="modelValue"
                            @change="$emit('update:modelValue', $event.target.value)"
                        >
                            <option
                                v-for="option in options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>`,
                    props: [
                        'modelValue',
                        'options',
                    ],
                },
                'sw-highlight-text': true,
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory,
                acl,
            },
        },
    });
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
        expect(wrapper.vm.measurementUnits).toEqual({
            system: 'metric',
            units: {
                length: 'mm',
                weight: 'kg',
            },
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
                name: 'Centimeter',
            },
        };

        const formattedLabel = wrapper.vm.formatUnitLabel(unit);
        expect(formattedLabel).toBe('Centimeter (cm)');
    });

    it('should handle empty unit in formatUnitLabel', async () => {
        const wrapper = await createWrapper();
        const formattedLabel = wrapper.vm.formatUnitLabel(null);
        expect(formattedLabel).toBe('');
    });

    it('should correctly update units on onMeasurementSystemChange', async () => {
        const wrapper = await createWrapper(null, {}, ['sales_channel.editor']);

        const systemInput = wrapper.findAll('.mt-select__input').at(0);
        await systemInput.setValue('imperial');
        await flushPromises();

        expect(wrapper.vm.measurementUnits.units).toEqual({
            length: 'in',
            weight: 'lb',
        });
    });

    it('should update measurement units when measurement system changes', async () => {
        const wrapper = await createWrapper(
            null,
            {
                system: 'imperial',
                units: {
                    length: 'in',
                    weight: 'lb',
                },
            },
            ['sales_channel.editor'],
        );

        expect(wrapper.vm.measurementUnits.system).toBe('imperial');
        expect(wrapper.vm.measurementUnits.units).toEqual({
            length: 'in',
            weight: 'lb',
        });

        const weightInput = wrapper.findAll('.mt-select__input').at(0);
        await weightInput.setValue('metric');
        await flushPromises();

        expect(wrapper.vm.measurementUnits.units).toEqual({
            length: 'mm',
            weight: 'kg',
        });
    });

    it('should update lengthUnit prop on salesChannel when sw-single-select for length changes', async () => {
        const measurementSystemRepository = {
            search: jest.fn(() => {
                return Promise.resolve(
                    new EntityCollection('/measurement-system', 'measurement_system', null, {}, [
                        {
                            id: 'metric',
                            name: 'Metric system',
                            technicalName: 'metric',
                            units: new EntityCollection('/measurement-display-unit', 'measurement_display_unit', null, {}, [
                                {
                                    id: 'm',
                                    type: 'length',
                                    measurementSystemId: 'metric',
                                    shortName: 'm',
                                    name: 'Meter',
                                    default: false,
                                },
                                {
                                    id: 'cm',
                                    type: 'length',
                                    measurementSystemId: 'metric',
                                    shortName: 'cm',
                                    name: 'Centimeter',
                                    default: true,
                                },
                                {
                                    id: 'mm',
                                    type: 'length',
                                    measurementSystemId: 'metric',
                                    shortName: 'mm',
                                    name: 'Milimeter',
                                    default: false,
                                },
                                {
                                    id: 'kg',
                                    type: 'weight',
                                    measurementSystemId: 'metric',
                                    shortName: 'kg',
                                    name: 'Kilogram',
                                    default: true,
                                },
                            ]),
                        },
                    ]),
                );
            }),
            get: jest.fn().mockResolvedValue(),
        };

        const wrapper = await createWrapper(measurementSystemRepository, {}, ['sales_channel.editor']);
        await flushPromises();

        expect(wrapper.vm.lengthUnitOptions.length).toBeGreaterThan(0);

        const lengthInput = wrapper.findAll('.mt-select__input').at(1);
        await lengthInput.setValue('cm');
        await flushPromises();

        expect(wrapper.vm.salesChannel.measurementUnits.units).toEqual({
            length: 'cm',
            weight: 'kg',
        });
    });

    it('should update weightUnit prop on salesChannel when sw-single-select for weight changes', async () => {
        const measurementSystemRepository = {
            search: jest.fn(() => {
                return Promise.resolve(
                    new EntityCollection('/measurement-system', 'measurement_system', null, {}, [
                        {
                            id: 'metric',
                            name: 'Metric system',
                            technicalName: 'metric',
                            units: new EntityCollection('/measurement-display-unit', 'measurement_display_unit', null, {}, [
                                {
                                    id: 'kg',
                                    type: 'weight',
                                    measurementSystemId: 'metric',
                                    shortName: 'kg',
                                    name: 'Kilogram',
                                    default: true,
                                },
                                {
                                    id: 'g',
                                    type: 'weight',
                                    measurementSystemId: 'metric',
                                    shortName: 'g',
                                    name: 'Gram',
                                    default: false,
                                },
                                {
                                    id: 'mm',
                                    type: 'length',
                                    measurementSystemId: 'metric',
                                    shortName: 'mm',
                                    name: 'Milimeter',
                                    default: true,
                                },
                            ]),
                        },
                    ]),
                );
            }),
            get: jest.fn().mockResolvedValue(),
        };

        const wrapper = await createWrapper(measurementSystemRepository, {}, ['sales_channel.editor']);
        await flushPromises();

        expect(wrapper.vm.weightUnitOptions.length).toBeGreaterThan(0);

        const weightInput = wrapper.findAll('.mt-select__input').at(2);
        await weightInput.setValue('g');
        await flushPromises();

        expect(wrapper.vm.salesChannel.measurementUnits.units.weight).toBe('g');
    });

    it('formatUnitLabel should handle units without translated names', async () => {
        const wrapper = await createWrapper();
        const unit = {
            name: 'Inch',
            shortName: 'in',
        };
        expect(wrapper.vm.formatUnitLabel(unit)).toBe('Inch (in)');
    });
});
