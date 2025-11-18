/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import EntityCollection from '../../../../core/data/entity-collection.data';

const createWrapper = async (privileges = []) => {
    const mockDefaultUnits = new EntityCollection(
        '/measurement-system',
        'measurement_system',
        null,
        {},
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
                        { id: 'cm', type: 'length', measurementSystemId: 'metric', shortName: 'cm', default: false },
                        { id: 'kg', type: 'weight', measurementSystemId: 'metric', shortName: 'kg', default: true },
                        { id: 'g', type: 'weight', measurementSystemId: 'metric', shortName: 'g', default: false },
                    ],
                    4,
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
                getEntityName: () => 'measurement_system',
            },
        ],
        2,
        null,
    );

    const repositoryFactory = {
        create: () => ({
            search: jest.fn().mockResolvedValue({}),
            get: jest.fn().mockResolvedValue({}),
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
        await wrapTestComponent('sw-settings-measurement-default-units', {
            sync: true,
        }),
        {
            props: {
                measurementUnits: {
                    system: 'metric',
                    length: 'mm',
                    weight: 'kg',
                },
                measurementSystems: mockDefaultUnits,
                measurementSystem: mockDefaultUnits.first(),
            },
            global: {
                stubs: {
                    'sw-card': await wrapTestComponent('sw-card', {
                        sync: true,
                    }),
                    'sw-container': await wrapTestComponent('sw-container', {
                        sync: true,
                    }),
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
                    'i18n-t': {
                        template: '<div class="i18n-stub"><slot></slot></div>',
                    },
                    'sw-internal-link': true,
                },
                mocks: {
                    $t: (path) => {
                        const translations = {
                            'sw-settings-measurement.defaultUnits.system': 'system',
                        };
                        return translations[path] || path;
                    },
                },

                provide: {
                    repositoryFactory,
                    acl,
                },
            },
        },
    );
};

describe('src/module/sw-settings-measurement/component/sw-settings-measurement-default-units', () => {
    it('should be a Vue component', async () => {
        const wrapper = await createWrapper(['measurement.editor']);
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the component properly', async () => {
        const wrapper = await createWrapper(['measurement.editor']);

        expect(wrapper.find('.sw-settings-measurement-default-units').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-measurement-default-units__description').exists()).toBeTruthy();

        const swSingleSelect = wrapper.findAll('.mt-select__input');
        expect(swSingleSelect).toHaveLength(3);

        expect(swSingleSelect.at(0).attributes().value).toBe('metric');

        expect(swSingleSelect.at(1).attributes().value).toBe('mm');
        expect(swSingleSelect.at(2).attributes().value).toBe('kg');
    });

    it('should emit measurement-system-change event when measurement system changes', async () => {
        const wrapper = await createWrapper(['measurement.editor']);
        const selects = wrapper.findAll('.mt-select__input');

        await selects.at(0).setValue('imperial');
        await flushPromises();

        expect(wrapper.emitted('measurement-system-change')).toBeTruthy();
        expect(wrapper.emitted('measurement-system-change')[0][0]).toBe('imperial');
    });

    it('should format unit label correctly', async () => {
        const wrapper = await createWrapper(['measurement.editor']);

        const item = {
            name: 'Meter',
            shortName: 'm',
            translated: {
                name: 'Meter',
            },
        };

        const formattedLabel = wrapper.vm.labelUnitCallback(item);
        expect(formattedLabel).toBe('Meter (m)');
    });

    it('should handle null values in label callbacks', async () => {
        const wrapper = await createWrapper(['measurement.editor']);

        expect(wrapper.vm.labelUnitCallback(null)).toBe('');
    });
});
