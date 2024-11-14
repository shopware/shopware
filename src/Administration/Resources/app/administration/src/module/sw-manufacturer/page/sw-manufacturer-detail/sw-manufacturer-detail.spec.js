/**
 * @package inventory
 */

import { mount } from '@vue/test-utils';

const mockProductId = 'MOCK_PRODUCT_ID';
let productGetShouldFail = false;
const productManufacturerRepositoryMock = {
    get: async () => {
        if (productGetShouldFail) {
            return Promise.reject();
        }
        return Promise.resolve({
            id: mockProductId,
        });
    },
    create: async () => Promise.resolve({}),
};

const mockCustomFieldSetId = 'MOCK_CUSTOM_FIELD_SET_ID';
let customFieldSetSearchShouldFail = false;
const customFieldSetRepositoryMock = {
    search: () => {
        if (customFieldSetSearchShouldFail) {
            return Promise.reject();
        }
        return Promise.resolve([{ id: mockCustomFieldSetId }]);
    },
    create: () => Promise.resolve({}),
};

const defaultRepositoryMock = {
    search: () => Promise.resolve({}),
    create: () => Promise.resolve({}),
};

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-manufacturer-detail', { sync: true }), {
        global: {
            data() {
                return {
                    isLoading: false,
                    manufacturer: {
                        mediaId: null,
                        link: 'https://google.com/doodles',
                        name: 'What does it means?(TM)',
                        description: null,
                        customFields: null,
                        apiAlias: null,
                        id: 'id',
                    },
                };
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"></slot><slot name="content">CONTENT</slot></div>',
                },
                'sw-media-upload-v2': true,
                'sw-text-editor': {
                    template: '<div class="sw-text-editor"/>',
                },
                'sw-card': {
                    template: '<div class="sw-card"><slot /></div>',
                },
                'sw-text-field': {
                    template: '<div class="sw-field"/>',
                },
                'sw-card-view': {
                    template: '<div><slot /></div>',
                },
                'sw-custom-field-set-renderer': true,
                'sw-upload-listener': true,
                'sw-button-process': true,
                'sw-language-info': true,
                'sw-empty-state': true,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button': true,
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-context-menu-item': true,
                'sw-sidebar-media-item': true,
                'sw-sidebar': true,
            },
            provide: {
                acl: {
                    can: (key) => (key ? privileges.includes(key) : true),
                },
                stateStyleDataProviderService: {},
                repositoryFactory: {
                    create: (repositoryName) => {
                        switch (repositoryName) {
                            case 'product_manufacturer':
                                return productManufacturerRepositoryMock;
                            case 'media':
                                return defaultRepositoryMock;
                            case 'custom_field_set':
                                return customFieldSetRepositoryMock;
                            default:
                                throw new Error(`${repositoryName} Repository not found`);
                        }
                    },
                },
            },
            mocks: {
                $route: {},
            },
            propsData: {
                manufacturerId: 'id',
            },
        },
    });
}

describe('src/module/sw-manufacturer/page/sw-manufacturer-detail', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to save edit', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
        ]);
        await flushPromises();

        const addButton = wrapper.find('.sw-manufacturer-detail__save-action');
        expect(addButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to save edit', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.sw-manufacturer-detail__save-action');
        expect(addButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit the manufacturer', async () => {
        const wrapper = await createWrapper([
            'product_manufacturer.editor',
        ]);
        await flushPromises();

        const logoUpload = wrapper.find('.sw-manufacturer-detail__logo-upload');
        expect(logoUpload.exists()).toBeTruthy();
        expect(logoUpload.attributes('disabled')).toBeFalsy();

        const elements = wrapper.findAll('.sw-field');
        expect(elements).toHaveLength(2);
        elements.forEach((el) => expect(el.attributes().disabled).toBeUndefined());

        const textEditor = wrapper.find('.sw-text-editor');
        expect(textEditor.exists()).toBeTruthy();
        expect(textEditor.attributes().disabled).toBeUndefined();
    });

    it('should not be able to edit the manufacturer', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const logoUpload = wrapper.find('.sw-manufacturer-detail__logo-upload');
        expect(logoUpload.exists()).toBeTruthy();
        expect(logoUpload.attributes('disabled')).toBeTruthy();

        const elements = wrapper.findAll('.sw-field');
        expect(elements).toHaveLength(2);
        elements.forEach((el) => expect(el.attributes().disabled).toBe('true'));

        const textEditor = wrapper.find('.sw-text-editor');
        expect(textEditor.exists()).toBeTruthy();
        expect(textEditor.attributes().disabled).toBeTruthy();
    });

    it('should fails complete loading if the product request fails', async () => {
        productGetShouldFail = true;
        customFieldSetSearchShouldFail = false;

        const wrapper = await createWrapper();
        await wrapper.setProps({
            manufacturerId: 'id-123',
        });

        wrapper.vm.createNotificationError = jest.fn();

        await flushPromises();
        expect(wrapper.vm.isLoading).toBe(false);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });

        expect(wrapper.vm.customFieldSets).toEqual([
            { id: 'MOCK_CUSTOM_FIELD_SET_ID' },
        ]);
    });

    it('should set loading to false if only the custom field set request fails', async () => {
        productGetShouldFail = false;
        customFieldSetSearchShouldFail = true;

        const wrapper = await createWrapper();
        await wrapper.setProps({
            manufacturerId: 'id-123',
        });
        wrapper.vm.createNotificationError = jest.fn();

        await flushPromises();
        expect(wrapper.vm.isLoading).toBe(false);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });
    });

    it('should set loading to false if both requests fail', async () => {
        productGetShouldFail = true;
        customFieldSetSearchShouldFail = true;

        const wrapper = await createWrapper();
        await wrapper.setProps({
            manufacturerId: 'id-123',
        });
        wrapper.vm.createNotificationError = jest.fn();

        await flushPromises();
        expect(wrapper.vm.isLoading).toBe(false);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });

        expect(wrapper.vm.customFieldSets).toEqual([]);
    });
});
