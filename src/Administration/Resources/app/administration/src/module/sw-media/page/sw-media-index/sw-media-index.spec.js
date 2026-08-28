/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

async function createWrapper({ route, props = {} } = {}) {
    const $route = reactive(route ?? { query: {} });

    return mount(await wrapTestComponent('sw-media-index', { sync: true }), {
        props,
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot></div>',
                },
                'sw-search-bar': true,
                'sw-media-sidebar': true,
                'sw-upload-listener': true,
                'sw-language-switch': true,
                'router-link': true,
                'sw-media-upload-v2': true,
                'sw-media-library': true,
                'sw-loader': true,
            },
            mocks: {
                $route,
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
                        },
                    }),
                },
                mediaService: {},
            },
        },
    });
}
describe('src/module/sw-media/page/sw-media-index', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should contain the default accept value', async () => {
        const wrapper = await createWrapper();
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('*/*');
    });

    it('should contain "application/pdf" value', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            fileAccept: 'application/pdf',
        });
        const fileInput = wrapper.find('sw-media-upload-v2-stub');
        expect(fileInput.attributes()['file-accept']).toBe('application/pdf');
    });

    it('should not be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.viewer'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');
        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to upload a new medium', async () => {
        global.activeAclRoles = ['media.creator'];

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('sw-media-upload-v2-stub');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('refreshes the list when the last upload finishes', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('refreshes the list when the last upload fails', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 1;

        wrapper.vm.onUploadFailed({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
    });

    it('does not refresh the list before all uploads are finished', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.reloadList = jest.fn();
        wrapper.vm.uploads = [{ id: 'upload-id' }];
        wrapper.vm.pendingUploadsCount = 2;

        wrapper.vm.onUploadFinished({ targetId: 'upload-id' });

        expect(wrapper.vm.reloadList).not.toHaveBeenCalled();
        expect(wrapper.vm.uploads).toHaveLength(0);
        expect(wrapper.vm.pendingUploadsCount).toBe(1);
    });

    it('seeds the search term from the initial route query', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'logo.png' } } });

        expect(wrapper.vm.term).toBe('logo.png');
    });

    it('syncs the search term when the route query.term changes in the same folder', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$route.query = { term: 'logo.png' };
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.term).toBe('logo.png');
        expect(wrapper.vm.selectedItems).toHaveLength(0);
    });

    it('adopts the route query.term when the folder changes', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'first.png' } } });
        expect(wrapper.vm.term).toBe('first.png');

        // Simulate clicking a search suggestion that targets a different folder
        // with a different term — both the routeFolderId prop and $route.query.term change.
        wrapper.vm.$route.query = { term: 'second.png' };
        await wrapper.setProps({ routeFolderId: 'folder-id' });

        expect(wrapper.vm.term).toBe('second.png');
    });

    it('clears the search term when the folder changes without a route query.term', async () => {
        const wrapper = await createWrapper({ route: { query: { term: 'first.png' } } });
        expect(wrapper.vm.term).toBe('first.png');

        wrapper.vm.$route.query = {};
        await wrapper.setProps({ routeFolderId: 'folder-id' });

        expect(wrapper.vm.term).toBe('');
    });
});
