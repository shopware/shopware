/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils_v2';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-radio-field';

const createWrapper = async () => {
    const baseComponent = {
        template: `
            <sw-radio-field :options="options" v-model="currentValue" :block="block" :description="description">
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
                    { value: 3, name: 'option 3' },
                ],
                currentValue: 1,
                block: false,
                description: null,
            };
        },
    };

    return shallowMount(baseComponent, {
        stubs: {
            'sw-radio-field': await Shopware.Component.build('sw-radio-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': {
                template: '<div></div>',
            },
        },
    });
};

describe('components/form/sw-radio-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should renders correctly with children slot', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should render number of children equal to option props', async () => {
        const wrapper = await createWrapper();

        const radioInputs = wrapper.findAll('.sw-field__radio-option');
        expect(radioInputs).toHaveLength(wrapper.vm.options.length);
    });

    it('should pass slot scoped to children slot correctly', async () => {
        const wrapper = await createWrapper();
        const customSlot = wrapper.find('#custom-slot');

        await wrapper.setData({ currentValue: 1 });
        await wrapper.vm.$nextTick();

        expect(customSlot.attributes('disabled')).toBeUndefined();

        await wrapper.setData({ currentValue: 2 });
        await wrapper.vm.$nextTick();

        expect(customSlot.attributes('disabled')).toBe('disabled');
    });

    it('should render description block', async () => {
        const wrapper = await createWrapper();

        let description = wrapper.find('.sw-field__radio-description');
        expect(description.exists()).toBe(false);

        await wrapper.setData({ description: 'Lorem ipsum' });

        description = wrapper.find('.sw-field__radio-description');
        expect(description.exists()).toBe(true);
    });

    it('should render description of options', async () => {
        const wrapper = await createWrapper();

        let optionDescription = wrapper.find('.sw-field__radio-option-description');
        expect(optionDescription.exists()).toBe(false);

        await wrapper.setData({
            options: [
                { value: 1, name: 'option 1', description: 'option 1' },
                { value: 2, name: 'option 2', description: 'option 2' },
                { value: 3, name: 'option 3', description: 'option 3' },
            ],
        });

        optionDescription = wrapper.find('.sw-field__radio-option-description');
        expect(optionDescription.exists()).toBe(true);
    });

    it('should show the label from the property', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-radio-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-radio-field'), {
            propsData: {
                label: 'Label from prop',
            },
            stubs: {
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': {
                    template: '<div></div>',
                },
            },
            scopedSlots: {
                label: '<template>Label from slot</template>',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });
});
