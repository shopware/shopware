import flowState from 'src/module/sw-flow/state/flow.state';

import EntityCollection from 'src/core/data/entity-collection.data';

import { mount } from '@vue/test-utils';

/**
 * @package services-settings
 */

const fieldClasses = [
    '.sw-flow-event-change-confirm-modal__title',
    '.sw-flow-event-change-confirm-modal__text-confirmation',
    '.sw-flow-event-change-confirm-modal__confirm-button',
    '.sw-flow-event-change-confirm-modal__cancel-button',
];

const btnConfirmClass = '.sw-flow-event-change-confirm-modal__confirm-button';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-flow-event-change-confirm-modal', {
            sync: true,
        }),
        {
            props: {
                item: {
                    id: 'action-name',
                },
            },
            global: {
                stubs: {
                    'sw-button': {
                        template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                    },
                    'sw-icon': true,
                },
            },
        },
    );
}

describe('module/sw-flow/component/modals/sw-flow-event-change-confirm-modal', () => {
    Shopware.State.registerModule('swFlowState', {
        ...flowState,
    });

    it('should show element correctly', async () => {
        const wrapper = await createWrapper();

        fieldClasses.forEach((elementClass) => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should reset flow sequence when clicking on confirm button', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        Shopware.State.commit(
            'swFlowState/setSequences',
            new EntityCollection(
                '/flow_sequence',
                'flow_sequence',
                null,
                { isShopwareContext: true },
                [
                    {
                        id: '2',
                        actionName: '',
                        ruleId: null,
                        parentId: '1',
                        position: 1,
                        displayGroup: 1,
                        trueCase: false,
                        config: {
                            entity: 'Customer',
                            tagIds: ['123'],
                        },
                    },
                ],
                1,
                null,
            ),
        );

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(1);

        const buttonConfirm = wrapper.find(btnConfirmClass);
        await buttonConfirm.trigger('click');
        await flushPromises();

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(0);

        expect(wrapper.emitted()['modal-confirm']).toBeTruthy();
        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });
});
