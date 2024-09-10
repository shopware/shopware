/**
 * @package content
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-media-index', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true,
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
                $route: {
                    query: '',
                },
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

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
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
});
