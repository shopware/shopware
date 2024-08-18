import { mount } from '@vue/test-utils';

import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 * @group disabledCompat
 */

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
    return mount(await wrapTestComponent('sw-flow-generate-document-modal', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            search: () => Promise.resolve(documentTypeMock),
                        };
                    },
                },
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
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-highlight-text': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-field-error': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            sequence: {},
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
        await flushPromises();

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
        await flushPromises();

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
