/**
 * @package content
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/media/sw-media-upload-v2';
import 'src/app/component/media/sw-media-compact-upload-v2';

describe('src/app/component/media/sw-media-compact-upload-v2', () => {
    let wrapper;

    beforeEach(async () => {
        const localVue = createLocalVue();
        localVue.directive('droppable', {});

        wrapper = shallowMount(await Shopware.Component.build('sw-media-compact-upload-v2'), {
            localVue,
            stubs: {
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-media-url-form': true,
                'sw-media-preview-v2': true,
                'sw-context-menu-divider': true
            },
            provide: {
                repositoryFactory: {},
                configService: {
                    getConfig: () => Promise.resolve({ settings: { enableUrlFeature: false } }),
                },
                mediaService: {
                    addListener: () => {},
                    removeByTag: () => {},
                    removeListener: () => {},
                }
            },
            propsData: {
                uploadTag: 'my-upload'
            }
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the default accept value', async () => {
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('image/*');
    });

    it('should contain "application/pdf" value', async () => {
        await wrapper.setProps({
            fileAccept: 'application/pdf'
        });
        const fileInput = wrapper.find('.sw-media-upload-v2__file-input');

        expect(fileInput.attributes().accept).toBe('application/pdf');
    });

    it('should contain url upload form when input type is url-upload', async () => {
        await wrapper.setData({
            inputType: 'file-upload'
        });

        let urlForm = wrapper.find('.sw-media-upload-v2__url-form');
        let uploadBtn = wrapper.find('.sw-media-upload-v2__button.upload');

        expect(urlForm.exists()).toBeFalsy();
        expect(uploadBtn.exists()).toBeTruthy();

        await wrapper.setData({
            inputType: 'url-upload'
        });

        urlForm = wrapper.find('.sw-media-upload-v2__url-form');
        uploadBtn = wrapper.find('.sw-media-upload-v2__button.upload');

        expect(urlForm.exists()).toBeTruthy();
        expect(uploadBtn.exists()).toBeFalsy();
    });

    it('it should return a preview if sourceMultiSelect is true', async () => {
        await wrapper.setProps({
            allowMultiSelect: true,
            sourceMultiselect: [
                {
                    id: '1',
                    fileName: 'example',
                    fileExtension: 'jpg'
                }
            ]
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.mediaPreview).toEqual([
            {
                id: '1',
                fileName: 'example',
                fileExtension: 'jpg'
            }
        ]);
    });

    it('should show a fallback if sourceMultiSelect is null', async () => {
        await wrapper.setProps({
            allowMultiSelect: true,
            sourceMultiselect: null,
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.mediaPreview).toBeNull();
    });

    it('should show a preview in single mode when a file has been provided', async () => {
        await wrapper.setProps({
            allowMultiSelect: false,
            source: {
                fileName: 'example',
                fileExtension: 'jpg',
            }
        });

        expect(wrapper.vm.mediaPreview).toEqual({
            fileName: 'example',
            fileExtension: 'jpg'
        });
    });

    it('should show a fallback in single mode when no file has been provided', async () => {
        await wrapper.setProps({
            allowMultiSelect: false,
            source: null
        });

        expect(wrapper.vm.mediaPreview).toBeNull();
    });

    it('should emit event `selection-change` event when the modal is closed', () => {
        wrapper.vm.onModalClosed([
            {
                id: '1',
                fileName: 'hello-world',
                fileExtension: 'gif'
            }
        ]);

        const events = wrapper.emitted('selection-change');

        expect(events).toHaveLength(1);
        expect(events.at(0)).toEqual([
            [
                {
                    id: '1',
                    fileName: 'hello-world',
                    fileExtension: 'gif'
                }
            ],
            'my-upload'
        ]);
    });

    it('should return correct file name when using the File object', () => {
        const file = new File([''], 'example.jpg');

        const name = wrapper.vm.getFileName(file);

        expect(name).toEqual('example.jpg');
    });

    it('should return correct file name when using media object from the database', () => {
        const name = wrapper.vm.getFileName({
            fileName: 'example',
            fileExtension: 'jpg',
        });

        expect(name).toEqual('example.jpg');
    });

    it('should render optional remove button label when corresponding prop is passed', async () => {
        await wrapper.setProps({
            allowMultiSelect: true,
            sourceMultiselect: [
                {
                    id: '1',
                    fileName: 'example',
                    fileExtension: 'jpg'
                }
            ]
        });
        const removeButton = wrapper.find('.sw-media-upload-v2__delete-item-button');
        expect(removeButton.text()).toEqual('global.sw-product-image.context.buttonRemove');

        await wrapper.setProps({
            removeButtonLabel: 'test'
        });
        expect(removeButton.text()).toEqual('test');
    });

    it('should disable deletion option in context menu when the disableDeletion is enabled and multiselect source length is lower or equal 1', async () => {
        await wrapper.setProps({
            allowMultiSelect: true,
            disableDeletionForLastItem: {
                value: true,
                helpText: 'example'
            },
            sourceMultiselect: [{
                fileName: 'example',
                fileExtension: 'jpg',
            }]
        });

        expect(wrapper.find('.sw-context-menu-item__buttonRemove').attributes('disabled')).toBeTruthy();
    });
});
