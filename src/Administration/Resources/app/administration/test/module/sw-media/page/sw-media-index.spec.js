import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-media/page/sw-media-index';

describe('src/module/sw-media/page/sw-media-index', () => {
    function createWrapper(privileges = []) {
        const localVue = createLocalVue();
        localVue.directive('tooltip', {});
        localVue.directive('droppable', {});

        return shallowMount(Shopware.Component.build('sw-media-index'), {
            localVue,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-page': '<div><slot name="smart-bar-actions"></slot></div>',
                'sw-search-bar': true,
                'sw-media-sidebar': true,
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
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        }
                    })
                },
                mediaService: {},
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    }
                }
            }
        });
    }

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should contain the default accept value', () => {
        const wrapper = createWrapper();
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('*/*');
    });

    it('should contain "application/pdf" value', () => {
        const wrapper = createWrapper();
        wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes().fileaccept).toBe('application/pdf');
    });

    it('should not be able to upload a new medium', async () => {
        const wrapper = createWrapper([
            'media.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');
        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to upload a new medium', async () => {
        const wrapper = createWrapper([
            'media.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
    });
});
