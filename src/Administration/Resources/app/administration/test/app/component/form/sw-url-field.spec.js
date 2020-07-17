import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-url-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/field-base/sw-field-error';

describe('components/form/sw-url-field', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-url-field'), {
            stubs: {
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-icon': Shopware.Component.build('sw-icon')
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

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should validate the url correctly', () => {
        wrapper.find('.sw-url-input-field__input').setValue('www.test-domain.de');
        expect(wrapper.find('.sw-field__error').exists()).toBe(false);

        wrapper.find('.sw-url-input-field__input').setValue('#');
        expect(wrapper.find('.sw-field__error').exists()).toBe(true);
    });

    it('should set the urlPrefix correctly', () => {
        expect(wrapper.text()).toBe('https://');
        wrapper.find('.sw-field__url-input__prefix').trigger('click');
        expect(wrapper.text()).toBe('http://');
    });
});
