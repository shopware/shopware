import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-password-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';

describe('components/form/sw-password-field', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-password-field'), {
            stubs: {
                'sw-field': true,
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                'sw-icon': true
            },
            provide: {
                validationService: {}
            },
            mocks: {
                $tc: key => key
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('Should display placeholder as text', async () => {
        await wrapper.setProps({
            placeholder: 'Enter your password'
        });

        expect(wrapper.props('placeholder')).toBe('Enter your password');
        expect(wrapper.find('input').attributes().placeholder).toBe('Enter your password');
    });

    it('Should display placeholder as password', async () => {
        await wrapper.setProps({
            placeholder: 'ThirteenChars',
            placeholderIsPassword: true
        });

        expect(wrapper.props('placeholder')).toBe('ThirteenChars');
        expect(wrapper.find('input').attributes().placeholder).toBe('*************');
    });

    it('Should display placeholder as password without given placeholder prop', async () => {
        await wrapper.setProps({
            placeholderIsPassword: true
        });

        expect(wrapper.find('input').attributes().placeholder).toBe('******');
    });

    it('Should display entered password by switching type to text', async () => {
        const input = wrapper.find('input');

        expect(input.attributes().type).toBe('password');

        await wrapper.setData({
            showPassword: true
        });

        await input.setValue('Very secret password');

        expect(input.attributes().type).toBe('text');
        expect(input.element.value).toBe('Very secret password');
    });
});
