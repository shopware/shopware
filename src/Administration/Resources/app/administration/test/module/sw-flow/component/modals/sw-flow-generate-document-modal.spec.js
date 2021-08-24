import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-generate-document-modal';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

const documentTypeMock = [
    {
        technicalName: 'invoice',
        translated: { name: 'Invoice' },
        id: '1'
    },
    {
        technicalName: 'credit_note',
        translated: { name: 'Credit note' },
        id: '2'
    },
    {
        technicalName: 'storno',
        translated: { name: 'Storno bill' },
        id: '3'
    },
    {
        technicalName: 'delivery_note',
        translated: { name: 'Delivery note' },
        id: '4'
    }
];

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-generate-document-modal'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => Promise.resolve(documentTypeMock)
                    };
                }
            }
        },

        propsData: {
            sequence: {}
        },

        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-button': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>'
            },
            'sw-single-select': {
                model: {
                    prop: 'value',
                    event: 'change'
                },
                props: ['value'],
                template: `
                    <div class="sw-single-select">
                        <input
                            class="sw-single-select__selection-input"
                            :value="value"
                            @input="$emit('change', $event.target.value)"
                        />
                        <slot></slot>
                    </div>
                `
            }
        }
    });
}

describe('module/sw-flow/component/sw-flow-generate-document-modal', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should show validation if document type field is empty', async () => {
        const wrapper = createWrapper();

        const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
        await saveButton.trigger('click');

        const documentTypeSelect = wrapper.find('.sw-single-select');
        expect(documentTypeSelect.attributes('error')).toBeTruthy();

        const documentTypeInput = wrapper.find('.sw-single-select__selection-input');
        await documentTypeInput.setValue('delivery_note');
        await documentTypeInput.trigger('input');

        await saveButton.trigger('click');

        expect(documentTypeSelect.attributes('error')).toBeFalsy();
    });

    it('should emit process-finish when document type is selected', async () => {
        const wrapper = createWrapper();

        const documentTypeInput = wrapper.find('.sw-single-select__selection-input');
        await documentTypeInput.setValue('invoice');
        await documentTypeInput.trigger('input');

        const saveButton = wrapper.find('.sw-flow-generate-document-modal__save-button');
        await saveButton.trigger('click');

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                documentType: 'invoice',
                documentRangerType: 'document_invoice'
            }
        }]);
    });
});
