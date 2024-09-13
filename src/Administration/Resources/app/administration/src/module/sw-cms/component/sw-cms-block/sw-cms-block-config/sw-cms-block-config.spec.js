/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const block = {
    name: 'Block name',
    type: 'text',
    backgroundColor: '',
    backgroundMedia: {},
    backgroundMediaId: 'mediaId',
    backgroundMediaMode: '',
    removable: true,
};

jest.useFakeTimers();

const responses = global.repositoryFactoryMock.responses;
responses.addResponse({
    method: 'Post',
    url: '/search/media',
    status: 200,
    response: {
        data: [
            {
                id: 'newMediaId',
                attributes: {
                    id: 'newMediaId',
                    fileName: 'puppy',
                    mediaFolderId: 'newMediaFolderId',
                    mimeType: 'image/png',
                    fileExtension: 'png',
                },
                relationships: [],
            },
        ],
    },
});

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-block-config', {
        sync: true,
    }), {
        attachTo: document.body,
        props: {
            block,
        },
        global: {
            provide: {
                validationService: {},
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {
                            text: block,
                        };
                    },
                },
            },
            stubs: {
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-colorpicker': await wrapTestComponent('sw-colorpicker'),
                'sw-colorpicker-deprecated': await wrapTestComponent('sw-colorpicker-deprecated'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-field-error': true,
                'sw-icon': true,
                'sw-text-field': {
                    template: '<input class="sw-text-field" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                    props: ['value'],
                },
                'sw-media-compact-upload-v2': true,
                'sw-upload-listener': true,
                'sw-select-field': true,
                'sw-help-text': true,
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-block-config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPageState').setIsSystemDefaultLanguage(true);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to config block name', async () => {
        const wrapper = await createWrapper();
        const blockNameField = await wrapper.find('.sw-text-field');

        expect(wrapper.vm.block.name).toBe(block.name);
        await blockNameField.setValue('test');
        await blockNameField.trigger('input');

        jest.runAllTimers();

        expect(wrapper.vm.block.name).toBe('test');
    });

    it('should be able to remove all media', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.block.backgroundMediaId).toBe(block.backgroundMediaId);
        await wrapper.vm.removeMedia();
        expect(wrapper.vm.block.backgroundMediaId).toBeNull();
    });

    it('should be able to manually set background media', async () => {
        const wrapper = await createWrapper();
        const media = {
            id: 'mediaId',
        };

        expect(wrapper.vm.block.backgroundMediaId).toBe(block.backgroundMediaId);
        await wrapper.vm.onSetBackgroundMedia([media]);
        expect(wrapper.vm.block.backgroundMediaId).toBe(media.id);
    });

    it('should be able to set background media after upload', async () => {
        const wrapper = await createWrapper();
        const media = {
            targetId: 'newMediaId',
        };

        expect(wrapper.vm.block.backgroundMediaId).toBe(block.backgroundMediaId);
        await wrapper.vm.successfulUpload(media);
        expect(wrapper.vm.block.backgroundMediaId).toBe(media.targetId);
    });

    const eventEmittedDataProvider = [
        ['block-delete', 'onBlockDelete'],
        ['block-duplicate', 'onBlockDuplicate'],
    ];
    it.each(eventEmittedDataProvider)('should be able to push the %s event on delete', async (eventName, handler) => {
        const wrapper = await createWrapper();

        wrapper.vm[handler]();

        expect(wrapper.emitted()).toHaveProperty(eventName, [[block]]);
        expect(wrapper.vm.quickactionClasses).toEqual({ 'is--disabled': false });
    });

    it.each(eventEmittedDataProvider)('should not be able to push the %s event on delete, when quickactions are disabled', async (eventName, handler) => {
        Shopware.Store.get('cmsPageState').setIsSystemDefaultLanguage(false);
        const wrapper = await createWrapper();

        wrapper.vm[handler]();

        expect(wrapper.emitted()).not.toHaveProperty(eventName);
        expect(wrapper.vm.quickactionClasses).toEqual({ 'is--disabled': true });
    });
});
