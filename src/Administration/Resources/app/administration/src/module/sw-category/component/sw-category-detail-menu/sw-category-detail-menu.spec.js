/**
 * @package inventory
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-detail-menu', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-upload-listener': true,
                'sw-media-upload-v2': {
                    template: '<div class="sw-media-upload-v2"></div>',
                    props: ['disabled'],
                },
                'sw-switch-field': {
                    template:
                        '<input class="sw-switch-field" type="checkbox" :value="value" @change="$emit(\'update:value\', $event.target.checked)" />',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-text-editor': {
                    template: '<div class="sw-text-editor"></div>',
                    props: ['disabled'],
                },
                'sw-media-modal-v2': {
                    template: '<div class="sw-media-modal-v2"><button @click="onEmitSelection">Add media</button></div>',
                    methods: {
                        onEmitSelection() {
                            this.$emit('media-modal-selection-change', [
                                { id: 'id' },
                            ]);
                        },
                    },
                },
            },
        },
        props: {
            category: {
                id: 'id',
                visible: true,
                getEntityName: () => {},
            },
        },
    });
}

describe('src/module/sw-category/component/sw-category-detail-menu', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should enable the visibility switch field when the acl privilege is missing', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const switchField = wrapper.getComponent('.sw-switch-field');

        expect(switchField.props('disabled')).toBe(false);
    });

    it('should disable the visibility switch field when the acl privilege is missing', async () => {
        const wrapper = await createWrapper();

        const switchField = wrapper.getComponent('.sw-switch-field');

        expect(switchField.props('disabled')).toBe(true);
    });

    it('should enable the media upload', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const mediaUpload = wrapper.getComponent('.sw-media-upload-v2');

        expect(mediaUpload.props('disabled')).toBe(false);
    });

    it('should disable the media upload', async () => {
        const wrapper = await createWrapper();

        const mediaUpload = wrapper.getComponent('.sw-media-upload-v2');

        expect(mediaUpload.props('disabled')).toBe(true);
    });

    it('should enable the text editor for the description', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const textEditor = wrapper.getComponent('.sw-text-editor');

        expect(textEditor.props('disabled')).toBe(false);
    });

    it('should disable the text editor for the description', async () => {
        const wrapper = await createWrapper();

        const textEditor = wrapper.getComponent('.sw-text-editor');

        expect(textEditor.props('disabled')).toBe(true);
    });

    it('should open media modal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({ showMediaModal: true });

        const mediaModal = wrapper.find('.sw-media-modal-v2');

        expect(mediaModal.exists()).toBe(true);
    });

    it('should turn off media modal', async () => {
        const wrapper = await createWrapper();

        const mediaModal = wrapper.find('.sw-media-modal-v2');

        expect(mediaModal.exists()).toBeFalsy();
    });

    it('should be able to change category media', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.mediaRepository.get = jest.fn(() => Promise.resolve({ id: 'id' }));

        await wrapper.setData({ showMediaModal: true });
        const button = wrapper.find('.sw-media-modal-v2 button');
        await button.trigger('click');

        expect(wrapper.vm.mediaRepository.get).toHaveBeenCalledWith('id');
        expect(wrapper.vm.category.mediaId).toBe('id');

        wrapper.vm.mediaRepository.get.mockRestore();
    });

    it('should not change category media when selected media is null', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.mediaRepository.get = jest.fn(() => Promise.resolve({}));
        wrapper.vm.onMediaSelectionChange([]);

        expect(wrapper.vm.mediaRepository.get).not.toHaveBeenCalled();

        wrapper.vm.mediaRepository.get.mockRestore();
    });
});
