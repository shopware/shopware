/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import EntityCollection from '../../../../core/data/entity-collection.data';

async function createWrapper() {
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
        create: () => ({
            search: jest.fn().mockResolvedValue(mockDefaultUnits),
            get: jest.fn().mockResolvedValue(),
        }),
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
                },
            },
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container', {
                    sync: true,
                }),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', {
                    sync: true,
                }),
                'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-popover': await wrapTestComponent('sw-popover', { sync: true }),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-help-text': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-field-error': true,
                'sw-loader': true,
                'sw-product-variant-info': true,
                'sw-single-select': {
                    props: [
                        'value',
                    ],
                    template: `
                            <input
                                class="sw-single-select__input"
                                :value="value"
                                @input="$emit('update:value', $event.target.value)"
                            />
                        `,
                },
                'sw-highlight-text': true,
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory,
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
        const wrapper = await createWrapper();

        const selects = wrapper.findAll('.sw-entity-single-select');

        const selection = selects.at(0).find('.sw-entity-single-select__selection');

        await selection.trigger('click');
        await flushPromises();

        const typeElement = wrapper.findAll('.sw-select-result');
        await typeElement.at(1).trigger('click');
        await flushPromises();

        expect(wrapper.vm.measurementUnits.system).toBe('imperial');
        expect(wrapper.vm.measurementUnits.units).toEqual({
            length: 'in',
            weight: 'lb',
        });
    });

    it('should update lengthUnit prop on salesChannel when sw-single-select for length changes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.measurementSystemRepository.search = jest.fn(() => {
            return Promise.resolve(
                new EntityCollection('/measurement-system', 'measurement_system', null, {}, [
                    {
                        id: 'metric',
                        name: 'Metric system',
                        technicalName: 'metric',
                        units: new EntityCollection('/measurement-display-unit', 'measurement_display_unit', null, {}, [
                            { id: 'm', type: 'length', measurementSystemId: 'metric', shortName: 'm', default: false },
                            { id: 'cm', type: 'length', measurementSystemId: 'metric', shortName: 'cm', default: true },
                            { id: 'mm', type: 'length', measurementSystemId: 'metric', shortName: 'mm', default: false },
                        ]),
                    },
                ]),
            );
        });

        await flushPromises();

        expect(wrapper.vm.lengthUnits.length).toBeGreaterThan(0);

        const lengthInput = wrapper.findAll('.sw-single-select__input');
        await lengthInput[0].setValue('cm');

        expect(wrapper.vm.salesChannel.measurementUnits.units).toEqual({
            length: 'cm',
            weight: 'kg',
        });
    });

    it('should update weightUnit prop on salesChannel when sw-single-select for weight changes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.measurementSystemRepository.search = jest.fn(() => {
            return Promise.resolve(
                new EntityCollection('/measurement-system', 'measurement_system', null, {}, [
                    {
                        id: 'metric',
                        name: 'Metric system',
                        technicalName: 'metric',
                        units: new EntityCollection('/measurement-display-unit', 'measurement_display_unit', null, {}, [
                            { id: 'kg', type: 'weight', measurementSystemId: 'metric', shortName: 'kg', default: true },
                            { id: 'g', type: 'weight', measurementSystemId: 'metric', shortName: 'g', default: false },
                        ]),
                    },
                ]),
            );
        });

        await flushPromises();

        expect(wrapper.vm.weightUnits.length).toBeGreaterThan(0);

        const weightInput = wrapper.findAll('.sw-single-select__input');
        await weightInput[1].setValue('g');

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
