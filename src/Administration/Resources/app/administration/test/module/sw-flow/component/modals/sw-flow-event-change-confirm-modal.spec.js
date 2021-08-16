import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-flow/component/modals/sw-flow-event-change-confirm-modal';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

const fieldClasses = [
    '.sw-flow-event-change-confirm-modal__title',
    '.sw-flow-event-change-confirm-modal__text-confirmation',
    '.sw-flow-event-change-confirm-modal__confirm-button',
    '.sw-flow-event-change-confirm-modal__cancel-button'
];

const btnConfirmClass = '.sw-flow-event-change-confirm-modal__confirm-button';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-event-change-confirm-modal'), {
        propsData: {
            item: {
                id: 'action-name'
            }
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
            'sw-icon': true
        }
    });
}

describe('module/sw-flow/component/modals/sw-flow-event-change-confirm-modal', () => {
    Shopware.State.registerModule('swFlowState', {
        ...flowState
    });

    it('should show element correctly', async () => {
        const wrapper = createWrapper();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should emit to modal-confirm', async () => {
        const wrapper = createWrapper();

        const buttonConfirm = wrapper.find(btnConfirmClass);
        await buttonConfirm.trigger('click');

        expect(wrapper.emitted()['modal-confirm']).toBeTruthy();
        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });
});
