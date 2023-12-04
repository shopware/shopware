import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swFlowGenerateDocumentModal from 'src/module/sw-flow/component/modals/sw-flow-generate-document-modal';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-highlight-text';

import Vuex from 'vuex_v2';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Component.register('sw-flow-generate-document-modal', swFlowGenerateDocumentModal);

const documentTypeMock = [
    {
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
        id: '1',
    },
    {
        technicalName: 'credit_note',
        translated: { name: 'Credit note' },
        id: '2',
    },
    {
        technicalName: 'storno',
        translated: { name: 'Cancellation invoice' },
        id: '3',
    },
    {
        technicalName: 'delivery_note',
        translated: { name: 'Delivery note' },
        id: '4',
    },
];

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-generate-document-modal'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(documentTypeMock),
                    };
                },
            },
        },

        propsData: {
            sequence: {},
        },

        data() {
            return {
                documentTypesSelected: [],
                fieldError: null,
            };
        },

        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
            },
            'sw-multi-select': await Shopware.Component.build('sw-multi-select'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-highlight-text': true,
            'sw-label': true,
            'sw-icon': true,
            'sw-field-error': true,
        },
    });
}

describe('module/sw-flow/component/sw-flow-generate-document-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                invalidSequences: [],
                documentTypes: [],
            },
        });
    });

    it('should show validation if document multiple type field is empty', async () => {
        const wrapper = await createWrapper();
        const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
        await saveButton.trigger('click');

        const documentTypeSelect = wrapper.find('.sw-flow-generate-document-modal__type-multi-select');
        expect(documentTypeSelect.classes()).toContain('has--error');
        await wrapper.setData({
            documentTypesSelected: ['invoice'],
        });

        await saveButton.trigger('click');
        expect(documentTypeSelect.classes()).not.toContain('has--error');
    });

    it('should emit process-finish when document multiple type is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            documentTypesSelected: ['invoice', 'delivery_note'],
        });
        const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
        await saveButton.trigger('click');
        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                documentTypes: [
                    {
                        documentType: 'invoice',
                        documentRangerType: 'document_invoice',
                    },
                    {
                        documentType: 'delivery_note',
                        documentRangerType: 'document_delivery_note',
                    },
                ],
            },
        }]);
    });
});
