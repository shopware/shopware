import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-privilege-error/page/sw-privilege-error';

describe('src/module/sw-privilege-error/page/sw-privilege-error', () => {
    let wrapper;

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.filter('asset', value => value);

        wrapper = shallowMount(Shopware.Component.build('sw-privilege-error'), {
            localVue,
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>'
                },
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
                }
            },
            mocks: {
                $router: {
                    go: jest.fn()
                },
                $tc: v => v
            }
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show a back button', async () => {
        const backButton = wrapper.find('.sw-privilege-error__back-button');

        expect(backButton.text()).toContain('sw-privilege-error.general.goBack');
    });

    it('should go a page back when button is clicked', async () => {
        const backButton = wrapper.find('.sw-privilege-error__back-button');

        expect(wrapper.vm.$router.go).not.toHaveBeenCalled();

        await backButton.trigger('click');

        expect(wrapper.vm.$router.go).toHaveBeenCalledWith(-1);
    });
});
