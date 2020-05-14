import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/page/sw-media-index';

describe('src/module/sw-media/page/sw-media-index', () => {
    let wrapper;

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.directive('droppable', { });

        wrapper = shallowMount(Shopware.Component.build('sw-media-index'), {
            localVue,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-page': '<div><slot name="smart-bar-actions"></slot></div>',
                'sw-search-bar': true,
                'sw-media-sidebar': true,
                'sw-media-library': true,
                'sw-upload-listener': true,
                'sw-language-switch': true,
                'router-link': true,
                'sw-media-upload-v2': true
            },
            mocks: {
                $t: v => v,
                $tc: v => v,
                $route: {
                    query: ''
                }
            },
            provide: {
                repositoryFactory: { },
                mediaService: { }
            }
        });
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain the default accept value', () => {
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('*/*');
    });

    it('should contain "application/pdf" value', () => {
        wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('application/pdf');
    });
});
