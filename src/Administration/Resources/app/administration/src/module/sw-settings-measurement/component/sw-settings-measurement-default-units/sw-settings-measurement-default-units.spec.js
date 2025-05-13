/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

const createWrapper = async () => {
    return mount(
        await wrapTestComponent('sw-settings-measurement-default-units', {
            sync: true,
        }),
        {
            props: {
                measurementSystem: {
                    typeId: 'type-1',
                    lengthUnitId: 'length-1',
                    massUnitId: 'mass-1',
                },
                lengthUnitCriteria: {},
                massUnitCriteria: {},
            },
            global: {
                stubs: {
                    'sw-card': await wrapTestComponent('sw-card', {
                        sync: true,
                    }),
                    'sw-container': await wrapTestComponent('sw-container', {
                        sync: true,
                    }),
                    'sw-entity-single-select': {
                        props: [
                            'value',
                        ],
                        template: `
                            <input
                                class="sw-entity-single-select__input"
                                :value="value"
                                @input="$emit('update:value', $event.target.value)"
                            />
                        `,
                    },
                    'sw-highlight-text': true,
                },
                mocks: {
                    $t: (path) => {
                        const translations = {
                            'sw-settings-measurement.defaultUnits.system': 'system'
                        };
                        return translations[path] || path;
                    }
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

        const swEntitySingleSelect = wrapper.findAll('.sw-entity-single-select__input');
        expect(swEntitySingleSelect).toHaveLength(3);

        expect(swEntitySingleSelect[0].element.value).toBe('type-1');
        expect(swEntitySingleSelect[1].element.value).toBe('length-1');
        expect(swEntitySingleSelect[2].element.value).toBe('mass-1');
    });

    it('should emit measurement-system-change event when measurement system changes', async () => {
        const wrapper = await createWrapper();
        const selects = wrapper.findAll('.sw-entity-single-select__input');

        await selects[0].setValue('type-2');
        expect(wrapper.emitted('measurement-system-change')).toBeTruthy();
    });

    it('should format unit label correctly', async () => {
        const wrapper = await createWrapper();

        const item = {
            name: 'Meter',
            shortName: 'm',
            translated: {
                name: 'Meter'
            }
        };

        const formattedLabel = wrapper.vm.labelUnitCallback(item);
        expect(formattedLabel).toBe('Meter (m)');
    });

    it('should handle null values in label callbacks', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.labelUnitCallback(null)).toBe('');
    });
});
