import { shallowMount } from '@vue/test-utils';

import swGenericSocialMediaCard from 'src/module/sw-custom-entity/component/sw-generic-social-media-card';

Shopware.Component.register('sw-generic-social-media-card', swGenericSocialMediaCard);

const TEST_OG_TITLE = 'TEST_OG_Title';
const TEST_OG_DESCRIPTION = 'TEST_OG_Description';

const TEST_OG_IMAGE = {
    id: 'TEST_OG_IMAGE_ID',
    url: 'TEST_OG_IMAGE_SRC',
    alt: 'TEST_OG_IMAGE_ALT',
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-generic-social-media-card'), {
        stubs: {
            'sw-card': true,
            'sw-text-field': {
                template: '<input class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                props: ['value', 'label', 'help-text', 'placeholder', 'maxlength'],
            },
            'sw-textarea-field': {
                template: '<textarea class="sw-text-field" :value="value" @input="$emit(\'input\', $event.target.value)" />',
                props: ['value', 'label', 'help-text', 'placeholder', 'maxlength'],
            },
            'sw-media-upload-v2': {
                template: '<div class="sw-media-upload-v2"></div>',
                props: ['variant', 'upload-tag', 'source', 'allow-multi-select', 'caption'],
            },
            'sw-upload-listener': {
                template: '<div class="sw-upload-listener"></div>',
                props: ['uploadTag', 'auto-upload'],
            },
            'sw-media-modal-v2': {
                template: '<div class="sw-media-modal-v2"></div>',
                props: ['variant', 'caption', 'allowMultiSelect'],
            },
        },
        provide: {
            repositoryFactory: {
                create: (name) => {
                    if (name === 'media') {
                        return {
                            get: jest.fn((entityId) => {
                                if (entityId !== TEST_OG_IMAGE.id) {
                                    throw new Error(`Entity ${entityId} not found`);
                                }

                                return Promise.resolve(TEST_OG_IMAGE);
                            }),
                        };
                    }

                    throw new Error(`Repository ${name} not found`);
                },
            },
        },
    });
}

Shopware.Service().register('shopwareDiscountCampaignService', () => {
    return { isDiscountCampaignActive: jest.fn(() => true) };
});

/**
 * @package content
 */
describe('src/module/sw-custom-entity/component/sw-generic-social-media-car', () => {
    it('should display the ogTitle and allow changing it', async () => {
        const wrapper = await createWrapper();

        const ogTitleInput = wrapper.get('.sw-generic-social-media-card__og-title-input');
        const ogTitleDisplay = wrapper.findAll('.sw-generic-social-media-card__media-preview-content-title');

        expect(ogTitleInput.props()).toEqual({
            helpText: 'sw-landing-page.base.seo.helpTextMetaTitle',
            label: 'sw-landing-page.base.seo.labelSocialMediaTitle',
            maxlength: '255',
            placeholder: 'sw-landing-page.base.seo.placeholderSocialMediaTitle',
            value: '',
        });

        expect(ogTitleInput.props('value')).toBe('');
        expect(ogTitleDisplay.wrappers.map(element => element.text())).toEqual(['', '']);

        await ogTitleInput.setValue(TEST_OG_TITLE);
        expect(wrapper.emitted()).toEqual({ 'update:og-title': [[TEST_OG_TITLE]] });

        await wrapper.setProps({ ogTitle: TEST_OG_TITLE });

        expect(ogTitleInput.props('value')).toBe(TEST_OG_TITLE);
        expect(ogTitleDisplay.wrappers.map(element => element.text())).toEqual([TEST_OG_TITLE, TEST_OG_TITLE]);
    });

    it('should display the ogDescription and allow changing it', async () => {
        const wrapper = await createWrapper();

        const ogDescriptionInput = wrapper.get('.sw-generic-social-media-card__og-description-input');
        const ogDescriptionDisplay = wrapper.get('.sw-generic-social-media-card__media-preview-content-description');

        expect(ogDescriptionInput.props()).toEqual({
            helpText: 'sw-landing-page.base.seo.helpTextMetaDescription',
            label: 'sw-landing-page.base.seo.labelSocialMediaDescription',
            maxlength: '255',
            placeholder: 'sw-landing-page.base.seo.placeholderSocialMediaDescription',
            value: '',
        });
        expect(ogDescriptionDisplay.text()).toBe('');

        await ogDescriptionInput.setValue(TEST_OG_DESCRIPTION);
        expect(wrapper.emitted()).toEqual({ 'update:og-description': [[TEST_OG_DESCRIPTION]] });

        await wrapper.setProps({ ogDescription: TEST_OG_DESCRIPTION });

        expect(ogDescriptionInput.props('value')).toBe(TEST_OG_DESCRIPTION);
        expect(ogDescriptionDisplay.text()).toBe(TEST_OG_DESCRIPTION);
    });

    it('should allow uploading an og-image', async () => {
        const wrapper = await createWrapper();

        // media preview should be empty
        let imageElements = wrapper.findAll('.sw-generic-social-media-card__media-preview-image').wrappers;
        expect(imageElements.map(element => element.attributes())).toEqual([
            expect.not.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
            expect.not.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
        ]);

        // read the uploadTag
        const uploadTag = wrapper.vm.uploadTag;

        const uploadListener = wrapper.get('.sw-generic-social-media-card__og-image-upload-listener');
        expect(uploadListener.props()).toEqual({
            autoUpload: '',
            uploadTag,
        });

        // emit the upload event
        uploadListener.vm.$emit('media-upload-finish', { targetId: TEST_OG_IMAGE.id });

        expect(wrapper.emitted()).toEqual({ 'update:og-image-id': [[TEST_OG_IMAGE.id]] });

        await wrapper.setProps({ ogImageId: TEST_OG_IMAGE.id });
        await flushPromises();

        // media preview should now contain the uploaded image
        imageElements = wrapper.findAll('.sw-generic-social-media-card__media-preview-image').wrappers;
        expect(imageElements.map(element => element.attributes())).toEqual([
            expect.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
            expect.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
        ]);
    });

    it('should allow selecting an existing images as og-image', async () => {
        const wrapper = await createWrapper();

        // media preview should be empty and the media modal should not be open
        let imageElements = wrapper.findAll('.sw-generic-social-media-card__media-preview-image').wrappers;
        expect(imageElements.map(element => element.attributes())).toEqual([
            expect.not.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
            expect.not.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
        ]);
        expect(wrapper.find('sw-generic-social-media-card__media-modal').exists()).toBe(false);

        // read the uploadTag from the mediaUpload component
        const uploadTag = wrapper.vm.uploadTag;

        const mediaUpload = wrapper.get('.sw-generic-social-media-card__og-image-upload');
        expect(mediaUpload.props()).toStrictEqual({
            allowMultiSelect: false,
            caption: 'sw-cms.elements.general.config.caption.mediaUpload',
            source: null,
            uploadTag: uploadTag,
            variant: 'regular',
        });

        // open the media modal
        mediaUpload.vm.$emit('media-upload-sidebar-open');
        await wrapper.vm.$nextTick();

        const mediaModal = wrapper.get('.sw-generic-social-media-card__media-modal');
        expect(mediaModal.props()).toEqual({
            allowMultiSelect: false,
            caption: 'sw-cms.elements.general.config.caption.mediaUpload',
            variant: 'regular',
        });

        // select an image
        mediaModal.vm.$emit('media-modal-selection-change', [TEST_OG_IMAGE]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // it should use the provided entity instead of fetching it from the repository
        expect(wrapper.vm.mediaRepository.get).not.toHaveBeenCalled();

        // media preview should be updated and the media modal should be closed
        imageElements = wrapper.findAll('.sw-generic-social-media-card__media-preview-image').wrappers;
        expect(imageElements.map(element => element.attributes())).toEqual([
            expect.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
            expect.objectContaining({ src: TEST_OG_IMAGE.url, alt: TEST_OG_IMAGE.alt }),
        ]);
        expect(wrapper.emitted()).toEqual({ 'update:og-image-id': [[TEST_OG_IMAGE.id]] });

        // close the media modal
        mediaModal.vm.$emit('media-modal-close');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('sw-generic-social-media-card__media-modal').exists()).toBe(false);
    });

    it('should allow removing the og-image', async () => {
        const wrapper = await createWrapper();

        const mediaUpload = wrapper.get('.sw-generic-social-media-card__og-image-upload');

        // remove the image
        mediaUpload.vm.$emit('media-upload-remove-image');
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted()).toEqual({ 'update:og-image-id': [[null]] });
    });
});
