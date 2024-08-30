/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

async function createWrapper(additionalOptions = {}) {
    return mount(await wrapTestComponent('sw-password-field-deprecated', { sync: true }), {
        global: {
            stubs: {
                'sw-field': true,
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-icon': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                validationService: {},
            },
        },
        ...additionalOptions,
    });
}

describe('components/form/sw-password-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('Should display placeholder as text', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            placeholder: 'Enter your password',
        });

        expect(wrapper.props('placeholder')).toBe('Enter your password');
        expect(wrapper.find('input').attributes().placeholder).toBe('Enter your password');
    });

    it('Should display placeholder as password', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            placeholder: 'ThirteenChars',
            placeholderIsPassword: true,
        });

        expect(wrapper.props('placeholder')).toBe('ThirteenChars');
        expect(wrapper.find('input').attributes().placeholder).toBe('*************');
    });

    it('Should display placeholder as password without given placeholder prop', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({
            placeholderIsPassword: true,
        });

        expect(wrapper.find('input').attributes().placeholder).toBe('******');
    });

    it('Should display entered password by switching type to text', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const input = wrapper.find('input');

        expect(input.attributes().type).toBe('password');

        await wrapper.setData({
            showPassword: true,
        });

        await input.setValue('Very secret password');

        expect(input.attributes().type).toBe('text');
        expect(input.element.value).toBe('Very secret password');
    });

    it('should show the label from the property', async () => {
        const wrapper = await createWrapper({
            propsData: {
                label: 'Label from prop',
                value: null,
            },
        });

        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        const wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
                value: null,
            },
            slots: {
                label: '<template>Label from slot</template>',
            },
        });

        await flushPromises();

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });
});
