import 'src/app/mixin/notification.mixin';
import 'src/app/mixin/listing.mixin';
import 'src/module/sw-settings/mixin/sw-settings-list.mixin';
import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-document-list', {
        sync: true,
    }), {
        data() {
            return {
                items: [
                    {
                        documentTypeId: '9bdea3067c7044a4a3011f8424e65dc5',
                        name: 'cancellation_invoice',
                        filenamePrefix: 'cancellation_invoice_',
                        global: true,
                        id: 'e15ed1f5155945e1ace36d8837e2b36f',
                        documentType: {
                            name: 'Cancellation invoice',
                            technicalName: 'storno',
                            translated: { name: 'Cancellation invoice', customFields: [] },
                        },
                        salesChannels: [{
                            documentBaseConfigId: 'e15ed1f5155945e1ace36d8837e2b36f',
                            documentTypeId: '9bdea3067c7044a4a3011f8424e65dc5',
                        }],
                    }],
            };
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>',
                },
                'sw-card-view': {
                    template: '<div><slot/></div> ',
                },
                'sw-card': {
                    template: '<div><slot/><slot name="grid"/></div>',
                },
                'sw-grid': await wrapTestComponent('sw-grid'),
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
                'sw-icon': true,
                'sw-search-bar': true,
                'router-link': true,
                'sw-language-switch': true,
                'sw-checkbox-field': true,
            },
            provide: {
                stateStyleDataProviderService: {},
                acl: {
                    can: key => (key ? privileges.includes(key) : true),
                },
                repositoryFactory: {
                    create: () => ({ search: () => Promise.resolve([]) }),
                },
                searchRankingService: {},
            },
            mocks: {
                $route: { query: '' },
            },
        },
    });
}

describe('src/module/sw-settings-document/page/sw-settings-document-list/', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled create button', async () => {
        const wrapper = await createWrapper(['document.creator']);
        const addButton = wrapper.find('.sw-settings-document-list__add-document');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled create button', async () => {
        const wrapper = await createWrapper();
        const addButton = wrapper.find('.sw-settings-document-list__add-document');
        expect(addButton.attributes().disabled).toBe('true');
    });

    it('should be able to edit', async () => {
        const wrapper = await createWrapper([
            'document.editor',
        ]);
        await flushPromises();

        const editButton = wrapper.findComponent('.sw-document-list__edit-action');
        expect(editButton.exists()).toBeDefined();
        expect(editButton.props().disabled).toBe(false);
    });

    it('should not be able to edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const editButton = wrapper.findComponent('.sw-document-list__edit-action');
        expect(editButton.exists()).toBeDefined();
        expect(editButton.props().disabled).toBe(true);
    });


    it('should be able to delete', async () => {
        const wrapper = await createWrapper([
            'document.deleter',
        ]);
        await flushPromises();

        const deleteButton = wrapper.findComponent('.sw-document-list__delete-action');
        expect(deleteButton.exists()).toBeDefined();
        expect(deleteButton.props().disabled).toBe(false);
    });

    it('should not be able to delete', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const deleteButton = wrapper.findComponent('.sw-document-list__delete-action');
        expect(deleteButton.exists()).toBeDefined();
        expect(deleteButton.props().disabled).toBe(true);
    });
});
