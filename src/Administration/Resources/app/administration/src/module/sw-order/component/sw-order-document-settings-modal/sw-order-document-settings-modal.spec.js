import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-order/component/sw-order-document-settings-modal';
import 'src/app/component/media/sw-media-upload-v2';
import 'src/app/component/form/sw-file-input';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-group';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

const orderFixture = {
    id: '1234',
    documents: [],
    taxStatus: 'gross',
    orderNumber: '10000',
    amountNet: 80,
    amountGross: 100,
    lineItems: []
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-order-document-settings-modal'), {
        stubs: {
            'sw-modal': {
                template: '<div class="sw-modal"><slot></slot><slot name="modal-footer"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-text-field': true,
            'sw-datepicker': true,
            'sw-checkbox-field': true,
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-file-input': await Shopware.Component.build('sw-file-input'),
            'sw-media-upload-v2': await Shopware.Component.build('sw-media-upload-v2'),
            'sw-context-button': {
                template: '<div class="sw-context-button"><slot></slot></div>'
            },
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-button-group': await Shopware.Component.build('sw-button-group'),
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" @click="$emit('click', $event.target.value)">
                        <slot></slot>
                    </div>`
            },
            'sw-upload-listener': true,
            'sw-textarea-field': true,
            'sw-field-error': true,
            'sw-icon': true,
        },
        provide: {
            numberRangeService: {
                reserve: () => Promise.resolve({ number: 1000 })
            },
            mediaService: {
                addListener: () => {},
                removeByTag: () => {},
                removeListener: () => {}
            },
            repositoryFactory: {
                create: () => ({
                    get: (id) => {
                        return Promise.resolve(
                            {
                                id,
                                fileSize: 10000,
                                mimeType: 'application/pdf',
                            }
                        );
                    },
                    search: () => {
                        return Promise.resolve(new EntityCollection(
                            '',
                            '',
                            Shopware.Context.api,
                            null,
                            [{}],
                            1
                        ));
                    }
                }),
            },
            configService: {
                getConfig: () => Promise.resolve({
                    settings: {
                        enableUrlFeature: false
                    },
                })
            }
        },
        propsData: {
            order: orderFixture,
            isLoading: false,
            currentDocumentType: {},
            isLoadingDocument: false,
            isLoadingPreview: false,
        }
    });
}

describe('src/module/sw-order/component/sw-order-document-settings-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit `preview-show` event when click on Preview button', async () => {
        const wrapper = await createWrapper();
        const previewButton = wrapper.find('.sw-order-document-settings-modal__preview-button');

        await previewButton.trigger('click');

        expect(wrapper.emitted()['preview-show']).toBeTruthy();
    });

    it('should show file or hide custom document file when toggling Upload custom document', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        const wrapper = await createWrapper();
        const inputUploadCustomDoc = wrapper.find('input[name="sw-field--uploadDocument"]');

        await inputUploadCustomDoc.setChecked(true);

        expect(wrapper.find('sw-upload-listener-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-media-upload-v2').exists()).toBeTruthy();
    });

    it('should emit `create` event when click on Create button', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        const wrapper = await createWrapper();

        const createButton = wrapper.find('.sw-order-document-settings-modal__create');
        await createButton.trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
    });

    it('should emit `document-create` event when click on Create and send button', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        const wrapper = await createWrapper();

        const createAndSendButton = wrapper.find('.sw-order-document-settings-modal__send-button');
        await createAndSendButton.trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toEqual('send');
    });

    it('should emit `document-create` event when click on Create and download button', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        const wrapper = await createWrapper();

        const createAndSendButton = wrapper.find('.sw-order-document-settings-modal__download-button');
        await createAndSendButton.trigger('click');

        expect(wrapper.emitted()['document-create']).toBeTruthy();
        expect(wrapper.emitted()['document-create'][0][1]).toEqual('download');
    });

    it('should able to add file from media modal if media is suitable', async () => {
        const wrapper = await createWrapper();

        const customDocumentToggle = wrapper.find('input[name="sw-field--uploadDocument"]');
        await customDocumentToggle.setChecked(true);

        wrapper.vm.onAddMediaFromLibrary([
            {
                id: 'media1',
                fileSize: 10000,
                mimeType: 'application/pdf'
            }
        ]);

        expect(wrapper.vm.documentConfig.documentMediaFileId).toEqual('media1');
    });

    it('should able to add file uploaded from url if media is suitable', async () => {
        const wrapper = await createWrapper();

        const customDocumentToggle = wrapper.find('input[name="sw-field--uploadDocument"]');
        await customDocumentToggle.setChecked(true);

        await wrapper.vm.successfulUploadFromUrl(
            {
                targetId: 'media1'
            }
        );

        expect(wrapper.vm.documentConfig.documentMediaFileId).toEqual('media1');
    });

    it('should able to show modal title responding to document type', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_7530'];
        const wrapper = await createWrapper();

        await wrapper.setProps({
            currentDocumentType: {
                id: '1',
                name: 'Invoice',
                technicalName: 'invoice',
            }
        });

        const modal = wrapper.find('.sw-modal');
        expect(modal.attributes().title).toEqual('sw-order.documentModal.modalTitle - Invoice');
    });
});
