import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-icon';
import 'src/app/component/utils/sw-maxlength-progress';
import 'src/app/component/utils/sw-progress-bar';
import 'src/app/component/form/field-base/sw-field-error';

describe('components/form/sw-text-field', () => {
    let wrapper;
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-text-field'), {
            localVue,
            stubs: {
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-icon': Shopware.Component.build('sw-icon'),
                'sw-maxlength-progress': Shopware.Component.build('sw-maxlength-progress'),
                'sw-progress-bar': Shopware.Component.build('sw-progress-bar')
            },
            propsData: {
                maxLength: 25
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

    it('should show progress bar', () => {
        wrapper.find('input[type=text]').setValue('abcdefghijklmnopqrstuvwxyz');
        expect(wrapper.find('.sw-progress-bar').exists()).toBe(true);
    });
});
