import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-radio-field';

const createWrapper = () => {
    const baseComponent = {
        template: `
            <sw-radio-field :options="options" v-model="currentValue">
                <template #custom-field-1="{ option, disabled, checked }">
                    <input id="custom-slot" type="text" :disabled="disabled || !checked">
                    <p>Custom slot</p>
                </template>
            </sw-radio-field>
        `,

        data() {
            return {
                options: [
                    { value: 1, name: 'option 1' },
                    { value: 2, name: 'option 2' },
                    { value: 3, name: 'option 3' }
                ],
                currentValue: null
            };
        }
    };

    return shallowMount(baseComponent, {
        stubs: {
            'sw-radio-field': Shopware.Component.build('sw-radio-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': '<div></div>'
        }
    });
};

describe('components/form/sw-radio-field', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should renders correctly with children slot', () => {
        const wrapper = createWrapper();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should render number of children equal to option props', () => {
        const wrapper = createWrapper();

        const radioInputs = wrapper.findAll('.sw-field__radio-option');
        expect(radioInputs.length).toEqual(wrapper.vm.options.length);
    });

    it('should pass slot scoped to children slot correctly', async () => {
        const wrapper = createWrapper();
        const customSlot = wrapper.find('#custom-slot');

        wrapper.setData({ currentValue: 1 });
        await wrapper.vm.$nextTick();

        expect(customSlot.attributes('disabled')).toBeUndefined();

        wrapper.setData({ currentValue: 2 });
        await wrapper.vm.$nextTick();

        expect(customSlot.attributes('disabled')).toBe('disabled');
    });
});
