/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCategoryDetailMenu from 'src/module/sw-category/component/sw-category-detail-menu';

Shopware.Component.register('sw-category-detail-menu', swCategoryDetailMenu);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-category-detail-menu'), {
        stubs: {
            'sw-card': true,
            'sw-switch-field': true,
            'sw-upload-listener': true,
            'sw-media-upload-v2': true,
            'sw-text-editor': true,
            'sw-media-modal-v2': {
                template: '<div class="sw-media-modal-v2-mock"><button @click="onEmitSelection">Add media</button></div>',
                methods: {
                    onEmitSelection() {
                        this.$emit('media-modal-selection-change', [{ id: 'id' }]);
                    },
                },
            },
        },
        propsData: {
            category: {
                getEntityName: () => {},
            },
        },
    });
}

describe('src/module/sw-category/component/sw-category-detail-menu', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should enable the visibility switch field when the acl privilege is missing', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBeUndefined();
    });

    it('should disable the visibility switch field when the acl privilege is missing', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.find('sw-switch-field-stub');

        expect(switchField.attributes().disabled).toBe('true');
    });

    it('should enable the media upload', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');

        expect(mediaUpload.attributes().disabled).toBeUndefined();
    });

    it('should disable the media upload', async () => {
        const wrapper = await createWrapper();

        const mediaUpload = wrapper.find('sw-media-upload-v2-stub');

        expect(mediaUpload.attributes().disabled).toBe('true');
    });

    it('should enable the text editor for the description', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const textEditor = wrapper.find('sw-text-editor-stub');

        expect(textEditor.attributes().disabled).toBeUndefined();
    });

    it('should disable the text editor for the description', async () => {
        const wrapper = await createWrapper();

        const textEditor = wrapper.find('sw-text-editor-stub');

        expect(textEditor.attributes().disabled).toBe('true');
    });

    it('should open media modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ showMediaModal: true });

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');

        expect(mediaModal.exists()).toBeTruthy();
    });

    it('should turn off media modal', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ showMediaModal: false });

        const mediaModal = wrapper.find('.sw-media-modal-v2-mock');

        expect(mediaModal.exists()).toBeFalsy();
    });

    it('should be able to change category media', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.mediaRepository.get = jest.fn(() => Promise.resolve({ id: 'id' }));

        await wrapper.setData({ showMediaModal: true });
        const button = wrapper.find('.sw-media-modal-v2-mock button');
        await button.trigger('click');

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith('id');
        expect(wrapper.vm.category.mediaId).toBe('id');

        wrapper.vm.mediaRepository.get.mockRestore();
    });

    it('should not change category media when selected media is null', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.mediaRepository.get = jest.fn(() => Promise.resolve({}));
        wrapper.vm.onMediaSelectionChange([]);

        expect(wrapper.vm.mediaRepository.get).not.toHaveBeenCalled();

        wrapper.vm.mediaRepository.get.mockRestore();
    });
});
