import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/field-base/sw-field-error';


const createWrapper = async (options) => {
    return shallowMount(await Shopware.Component.build('sw-field-error'), {
        mocks: {
            $tc: (key, number, value) => {
                if (!value || Object.keys(value).length < 1) {
                    return key;
                }
                return key + JSON.stringify(value);
            },
        },
        stubs: {
            'sw-icon': true,
        },
        ...options,
    });
};
describe('src/app/component/form/field-base/sw-field-error', () => {
    it('should render error message when error is provided', async () => {
        const errorMessage = 'This is an error message';
        const wrapper = await createWrapper({
            propsData: {
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: errorMessage,
                },
            },
        });

        expect(wrapper.find('.sw-field__error').exists()).toBe(true);
        expect(wrapper.find('.sw-field__error').text()).toContain(errorMessage);
    });

    it('should not render error message when error is not provided', async () => {
        const wrapper = await createWrapper({
            propsData: {
                error: null,
            },
        });

        expect(wrapper.find('.sw-field__error').exists()).toBe(false);
    });

    it('should format parameters correctly', async () => {
        const errorMessage = 'This is an error message with parameter: Test Parameter';
        const wrapper = await createWrapper({
            propsData: {
                error: {
                    code: 'SOME_ERROR_CODE',
                    detail: errorMessage,
                    parameters: {
                        '{{ parameter }}': 'Test Parameter',
                    },
                },
            },
        });

        expect(wrapper.find('.sw-field__error').exists()).toBe(true);
        expect(wrapper.find('.sw-field__error').text()).toContain('global.error-codes.SOME_ERROR_CODE{\"parameter\":\"Test Parameter\"}');
    });
});
