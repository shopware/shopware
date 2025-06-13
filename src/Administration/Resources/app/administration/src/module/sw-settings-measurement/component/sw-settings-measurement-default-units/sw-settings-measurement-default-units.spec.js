/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';
import EntityCollection from '../../../../core/data/entity-collection.data';

const { Criteria } = Shopware.Data;

const createWrapper = async () => {
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

    const measurementSystemCriteria = () => {
        const criteria = new Criteria(1, null);
        criteria.addAssociation('units');

        return criteria;
    };

    const repositoryFactory = {
        create: () => ({
            search: jest.fn().mockResolvedValue(mockDefaultUnits),
            get: jest.fn().mockResolvedValue(mockDefaultUnits.first()),
        }),
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
                measurementSystem: mockDefaultUnits.first(),
                measurementSystemCriteria: measurementSystemCriteria(),
            },
            global: {
                stubs: {
                    'sw-card': await wrapTestComponent('sw-card', {
                        sync: true,
                    }),
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
                },
            },
        },
    );
};

describe('src/module/sw-settings-measurement/component/sw-settings-measurement-default-units', () => {
    it('should be a Vue component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the component properly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-settings-measurement-default-units').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-measurement-default-units__description').exists()).toBeTruthy();

        const swEntitySingleSelect = wrapper.findAll('.sw-entity-single-select');
        expect(swEntitySingleSelect).toHaveLength(1);

        const swSingleSelect = wrapper.findAll('.sw-single-select__input');
        expect(swSingleSelect).toHaveLength(2);

        expect(swEntitySingleSelect[0].find('.sw-entity-single-select__selection').text()).toBe('Metric system');

        expect(swSingleSelect[0].element.value).toBe('mm');
        expect(swSingleSelect[1].element.value).toBe('kg');
    });

    it('should emit measurement-system-change event when measurement system changes', async () => {
        const wrapper = await createWrapper();
        const selects = wrapper.findAll('.sw-entity-single-select');

        const selection = selects.at(0).find('.sw-entity-single-select__selection');

        await selection.trigger('click');
        await flushPromises();

        const typeElement = wrapper.findAll('.sw-select-result');
        await typeElement.at(0).trigger('click');
        await flushPromises();

        expect(wrapper.emitted('measurement-system-change')).toBeTruthy();
        expect(wrapper.emitted('measurement-system-change')[0][0].id).toBe('metric');
        expect(wrapper.emitted('measurement-system-change')[0][0].name).toBe('Metric system');
        expect(wrapper.emitted('measurement-system-change')[0][0].technicalName).toBe('metric');
    });

    it('should format unit label correctly', async () => {
        const wrapper = await createWrapper();

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
        const wrapper = await createWrapper();

        expect(wrapper.vm.labelUnitCallback(null)).toBe('');
    });
});
