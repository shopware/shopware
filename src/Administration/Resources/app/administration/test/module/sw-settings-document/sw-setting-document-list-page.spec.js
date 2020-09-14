import 'src/app/component/grid/sw-grid';

import 'src/app/mixin/notification.mixin';
import 'src/app/mixin/listing.mixin';

import 'src/module/sw-settings/mixin/sw-settings-list.mixin';

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-document/page/sw-settings-document-list/';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-document-list'), {
        localVue,
        data() {
            return {
                items: [
                    {
                        documentTypeId: '9bdea3067c7044a4a3011f8424e65dc5',
                        name: 'storno',
                        filenamePrefix: 'storno_',
                        global: true,
                        id: 'e15ed1f5155945e1ace36d8837e2b36f',
                        documentType: {
                            name: 'Storno bill',
                            technicalName: 'storno',
                            translated: { name: 'Storno bill', customFields: [] }
                        },
                        salesChannels: [{
                            documentBaseConfigId: 'e15ed1f5155945e1ace36d8837e2b36f',
                            documentTypeId: '9bdea3067c7044a4a3011f8424e65dc5'
                        }]
                    }]
            };
        },
        stubs: {
            'sw-page': {
                template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>'
            },
            'sw-card-view': {
                template: '<div><slot/></div> '
            },
            'sw-card': {
                template: '<div><slot/><slot name="grid"/></div>'
            },
            'sw-grid': Shopware.Component.build('sw-grid'),
            'sw-grid-row': true,
            'sw-empty-state': true,
            'sw-button': true,
            'sw-loader': true,
            'sw-grid-column': true,
            'sw-context-button': true,
            'sw-context-menu-item': { template: '<div></div>', props: ['disabled'] },
            'sw-label': true,
            'sw-modal': true,
            'sw-pagination': true,
            'sw-icon ': true,
            'sw-search-bar': true,
            'router-link': true
        },
        provide: {
            stateStyleDataProviderService: {},
            acl: {
                can: key => (key ? privileges.includes(key) : true)
            },
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve([]) })
            }
        },
        mocks: {
            $tc: v => v,
            $route: { query: '' },
            $device: {
                onResize: () => {
                }
            },
            $router: {
                replace: () => {
                }
            }
        }
    });
}

describe('src/module/sw-settings-document/page/sw-settings-document-list/', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled create button', async () => {
        const wrapper = createWrapper(['document.creator']);
        const addButton = wrapper.find('.sw-settings-document-list__add-document');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = createWrapper();
        const addButton = wrapper.find('.sw-settings-document-list__add-document');
        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should be able to edit', async () => {
        const wrapper = createWrapper([
            'document.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editButton = wrapper.find('.sw-document-list__edit-action');
        expect(editButton.exists()).toBeTruthy();
        expect(editButton.props().disabled).toEqual(false);
    });

    it('should not be able to edit', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const editButton = wrapper.find('.sw-document-list__edit-action');
        expect(editButton.exists()).toBeTruthy();
        expect(editButton.props().disabled).toEqual(true);
    });


    it('should be able to delete', async () => {
        const wrapper = createWrapper([
            'document.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteButton = wrapper.find('.sw-document-list__delete-action');
        expect(deleteButton.exists()).toBeTruthy();
        expect(deleteButton.props().disabled).toEqual(false);
    });

    it('should not be able to delete', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const deleteButton = wrapper.find('.sw-document-list__delete-action');
        expect(deleteButton.exists()).toBeTruthy();
        expect(deleteButton.props().disabled).toEqual(true);
    });
});
