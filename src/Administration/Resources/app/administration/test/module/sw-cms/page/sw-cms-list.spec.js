import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/page/sw-cms-list';
import 'src/module/sw-cms/component/sw-cms-list-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';

function createWrapper() {
    const localVue = createLocalVue();

    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-cms-list'), {
        localVue,
        stubs: {
            'sw-page': '<div><slot name="content"></slot></div>',
            'sw-card-view': '<div><slot></slot></div>',
            'sw-tabs': '<div><slot name="content"></slot></div>',
            'sw-field': '<div></div>',
            'sw-icon': '<div></div>',
            'sw-pagination': '<div></div>',
            'sw-cms-list-item': Shopware.Component.build('sw-cms-list-item'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-popover': '<div><slot></slot></div>',
            'sw-context-menu': '<div><slot></slot></div>',
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
            'sw-media-modal-v2': '<div class="sw-media-modal-v2-mock"></div>'
        },
        mocks: {
            $tc: (value) => value,
            $router: { replace: () => {} },
            $route: { query: '' }
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve() })
            }
        }
    });
}

describe('module/sw-cms/page/sw-cms-list', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should open the media modal when user clicks on edit preview image', () => {
        const wrapper = createWrapper();

        wrapper.setData({
            pages: [
                {
                    sections: [],
                    categories: [],
                    translated: {
                        name: 'CMS Page 1'
                    }
                }
            ]
        });

        wrapper.find('.sw-cms-list-item--0 .sw-context-button__button')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeFalsy();

        wrapper.find('.sw-cms-list-item--0 .sw-cms-list-item__option-preview')
            .trigger('click');

        expect(wrapper.vm.showMediaModal).toBeTruthy();

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');
        expect(mediaModal.classes()).toContain('sw-media-modal-v2-mock');
    });
});
