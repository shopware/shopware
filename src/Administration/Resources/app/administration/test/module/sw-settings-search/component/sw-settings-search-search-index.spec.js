import { createLocalVue, shallowMount } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import 'src/module/sw-settings-search/component/sw-settings-search-search-index';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';
import 'src/app/mixin/notification.mixin';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-search-index'), {
        localVue,

        provide: {
            repositoryFactory: {
                create: (name) => {
                    if (name === 'product') {
                        return {
                            search: () => Promise.resolve([])
                        };
                    }

                    if (name === 'product_search_keyword') {
                        return {
                            search: () => Promise.resolve([{
                                versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                                productId: 'ced577ea267e4eaab52da40b2cf8c570',
                                productVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
                                keyword: 'a0254ce850054780bfb4a5b26d6c99cf',
                                ranking: 1000,
                                createdAt: '2021-02-15T12:47:08.464+00:00',
                                updatedAt: null,
                                apiAlias: null,
                                id: 'ffce0992117444529bf702c30f14ae3b'
                            }])
                        };
                    }

                    return null;
                }
            },
            productIndexService: {
                index: jest.fn((offset) => {
                    if (offset === 0) {
                        return Promise.resolve({
                            finish: false,
                            offset: {
                                offset: 51
                            }
                        });
                    }

                    if (offset === 51) {
                        return Promise.resolve({
                            finish: false,
                            offset: {
                                offset: 60
                            }
                        });
                    }

                    if (offset === 60) {
                        return Promise.resolve({
                            finish: true
                        });
                    }

                    return Promise.resolve({});
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }

        },

        stubs: {
            'sw-card': true,
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-progress-bar': {
                template: '<div class="sw-progress-bar"><slot></slot></div>'
            },
            'sw-alert': {
                template: '<div class="sw-alert"><slot></slot></div>'
            },
            'sw-icon': true
        }
    });
}

describe('module/sw-settings-search/component/sw-settings-search-search-index', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not able to rebuild the search index', async () => {
        const wrapper = createWrapper([
            'product_search_config.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const rebuildButton = wrapper.find('.sw-settings-search__search-index-rebuild-button');
        expect(rebuildButton.attributes().disabled).toBeTruthy();
    });


    it('should rebuild search index and show the notification on clicking the rebuild button', async () => {
        let response = {};
        const wrapper = createWrapper([
            'product_search_config.editor'
        ]);
        await wrapper.vm.$nextTick();
        wrapper.vm.createNotificationInfo = jest.fn();

        // First time call the update progress
        await wrapper.setData({
            offset: 0
        });
        const rebuildButton = wrapper.find('.sw-settings-search__search-index-rebuild-button');
        await rebuildButton.trigger('click');
        await flushPromises();
        expect(response.finish).toBeFalsy();

        // Expect to see the notification about index started
        expect(wrapper.vm.createNotificationInfo).toHaveBeenCalledWith({
            message: 'sw-settings-search.notification.index.started'
        });
        response = await wrapper.vm.productIndexService.index(wrapper.vm.offset);
        expect(response.offset.offset).toBe(51);
        expect(response.finish).toBeFalsy();


        // Second call with offset 51
        await wrapper.setData({
            offset: response.offset.offset
        });
        response = await wrapper.vm.productIndexService.index(wrapper.vm.offset);
        await flushPromises();
        expect(response.offset.offset).toBe(60);
        expect(response.finish).toBeFalsy();

        // Third call with offset 60
        await wrapper.setData({
            offset: response.offset.offset
        });
        response = await wrapper.vm.productIndexService.index(wrapper.vm.offset);
        await flushPromises();

        // To polling should be finished
        expect(response.finish).toBeTruthy();
        wrapper.vm.createNotificationInfo.mockRestore();
    });
});
