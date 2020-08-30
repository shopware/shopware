import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-maxlength-progress';
import 'src/app/component/utils/sw-progress-bar';

describe('components/utils/sw-maxlength-progress', () => {
    let wrapper;
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    beforeEach(() => {
        wrapper = shallowMount(Shopware.Component.build('sw-maxlength-progress'), {
            localVue,
            stubs: {
                'sw-maxlength-progress': Shopware.Component.build('sw-maxlength-progress'),
                'sw-progress-bar': Shopware.Component.build('sw-progress-bar')
            },
            propsData: {
                maxLength: 25,
                length: 5
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

    it('should render progress bar', () => {
        expect(wrapper.find('.sw-progress-bar').exists()).toBe(true);
    });

    it('should show', () => {
        expect(wrapper.find('.sw-maxlength-progress').classes('is--visible')).toBe(true);
    });

    it('should show from x chars', () => {
        wrapper.setProps({
            showFrom: 10
        });
        expect(wrapper.find('.sw-maxlength-progress').classes('is--visible')).toBe(false);
    });

    it('should turn red for full', () => {
        wrapper.setProps({
            maxLength: 25,
            length: 25
        });
        expect(wrapper.find('.sw-maxlength-progress').classes('is--full')).toBe(true);
        expect(wrapper.find('.sw-maxlength-progress').classes('is--warning')).toBe(false);
    });

    it('should turn orange for almost full', () => {
        wrapper.setProps({
            maxLength: 25,
            length: 22
        });
        expect(wrapper.find('.sw-maxlength-progress').classes('is--warning')).toBe(true);
        expect(wrapper.find('.sw-maxlength-progress').classes('is--full')).toBe(false);
    });
});
