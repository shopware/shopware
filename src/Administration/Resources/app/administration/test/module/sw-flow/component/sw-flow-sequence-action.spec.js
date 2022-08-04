import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-sequence-action';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-grouped-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';

import { ACTION } from 'src/module/sw-flow/constant/flow.constant';

import EntityCollection from 'src/core/data/entity-collection.data';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

const sequenceFixture = {
    id: '2',
    actionName: '',
    ruleId: null,
    parentId: '1',
    position: 1,
    displayGroup: 1,
    trueCase: false,
    config: {
        entity: 'Customer',
        tagIds: ['123']
    }
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        actionName: ACTION.ADD_TAG
    },
    {
        ...sequenceFixture,
        actionName: ACTION.MAIL_SEND,
        position: 2,
        id: '3'
    }
];

function getSequencesCollection(collection = []) {
    return new EntityCollection(
        '/flow_sequence',
        'flow_sequence',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null
    );
}

function createWrapper(propsData = {}, appFlowResponseData = [], flag = null) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-sequence-action'), {
        localVue,
        stubs: {
            'sw-icon': {
                template: '<div class="sw-icon" v-on="$listeners"></div>'
            },
            'sw-context-button': true,
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" v-on="$listeners">
                      <slot></slot>
                    </div>
                `
            },
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-grouped-single-select': Shopware.Component.build('sw-grouped-single-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>'
            },
            'sw-select-result': {
                props: ['item', 'index'],
                template: `<li class="sw-select-result" @click.stop="onClickResult">
                                <slot></slot>
                           </li>`,
                methods: {
                    onClickResult() {
                        this.$parent.$parent.$emit('item-select', this.item);
                    }
                }
            },
            'sw-highlight-text': {
                props: ['text'],
                template: '<div class="sw-highlight-text">{{ this.text }}</div>'
            },
            'sw-field-error': true,
            'sw-flow-sequence-modal': {
                props: ['sequence'],
                template: `<div class="sw-flow-sequence-modal" @click="onSaveActionSuccess">
                             <slot></slot>
                           </div>`,
                methods: {
                    onSaveActionSuccess() {
                        this.$emit('process-finish', {
                            ...this.sequence,
                            config: {
                                entity: 'Customer',
                                tagIds: ['123']
                            }
                        });
                    }
                }
            }
        },
        propsData: {
            sequence: sequenceFixture,
            ...propsData
        },

        provide: {
            repositoryFactory: {
                create: () => {
                    if (flag === 'appFlowAction') {
                        return {
                            create: () => {
                                return {};
                            },
                            search: () => Promise.resolve(appFlowResponseData)
                        };
                    }

                    return {
                        create: () => {
                            return {};
                        },
                        search: () => Promise.resolve([])
                    };
                }
            },
            flowBuilderService: {
                getActionTitle: (actionName) => {
                    return {
                        value: actionName
                    };
                },

                getActionModalName() {
                    return 'sw-flow-modal-name';
                },

                mapActionType: () => {}
            }
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-flow/component/sw-flow-sequence-action', () => {
    beforeAll(() => {
        Shopware.Service().register('flowBuilderService', () => {
            return {
                mapActionType: () => {}
            };
        });

        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }])
                },
                invalidSequences: [],
                mailTemplates: [],
                triggerEvent: {
                    data: {
                        customer: '',
                        order: ''
                    },
                    customerAware: true,
                    extensions: [],
                    mailAware: true,
                    name: 'checkout.customer.login',
                    aware: [
                        'Shopware\\Core\\Framework\\Event\\CustomerAware',
                        'Shopware\\Core\\Framework\\Event\\MailAware'
                    ]
                },
                triggerActions: [
                    { name: 'action.add.order.tag', requirements: ['Shopware\\Core\\Framework\\Event\\OrderAware'], extensions: [] },
                    { name: 'action.add.customer.tag', requirements: ['Shopware\\Core\\Framework\\Event\\CustomerAware'], extensions: [] },
                    { name: 'action.remove.customer.tag', requirements: ['Shopware\\Core\\Framework\\Event\\CustomerAware'], extensions: [] },
                    { name: 'action.remove.order.tag', requirements: ['Shopware\\Core\\Framework\\Event\\OrderAware'], extensions: [] },
                    { name: 'action.mail.send', requirements: ['Shopware\\Core\\Framework\\Event\\MailAware'], extensions: [] },
                    { name: 'action.set.order.state', requirements: ['Shopware\\Core\\Framework\\Event\\OrderAware'], extensions: [] },
                    { name: 'telegram.send.message', requirements: ['Shopware\\Core\\Framework\\Event\\CustomerAware'], extensions: [] },
                    { name: 'action.stop.flow', requirements: [], extensions: [] },
                ]
            }
        });
    });

    it('should able to add an action', async () => {
        const wrapper = createWrapper();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(0).trigger('click');

        const modal = wrapper.find('.sw-flow-sequence-modal');
        await modal.trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];

        const newSequence = {
            ...wrapper.props().sequence,
            actionName: wrapper.vm.actionOptions[0].value
        };

        expect(sequencesState[0]).toEqual(newSequence);

        const addButton = wrapper.find('.sw-flow-sequence-action__add-button');
        expect(addButton.exists()).toBeTruthy();
    });

    it('should show action list correctly', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        const actionItems = wrapper.findAll('.sw-flow-sequence-action__action-item');
        expect(actionItems.length).toEqual(2);
    });

    it('should show dynamic modal', async () => {
        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        const addButton = wrapper.find('.sw-flow-sequence-action__add-button');
        expect(addButton.exists()).toBeTruthy();

        await addButton.trigger('click');

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(0).trigger('click');
        const modalElement = wrapper.find('.sw-flow-sequence-modal');

        expect(modalElement.exists()).toBeTruthy();
    });

    it('should not able to add more actions if existing action is stop flow', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: ACTION.STOP_FLOW
            }
        });

        const addAction = wrapper.find('.sw-flow-sequence-action__add-action');
        expect(addAction.exists()).toBeFalsy();
    });

    it('should able to remove action container', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(2);


        const deleteContainer = wrapper.find('.sw-flow-sequence-action__delete-action-container');
        await deleteContainer.trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(0);
    });

    it('should able to remove an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(2);


        const deleteActions = wrapper.findAll('.sw-flow-sequence-action__delete-action');
        await deleteActions.at(0).trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(1);
        expect(sequencesState[0]).toEqual(sequencesFixture[1]);
    });

    it('should set error for single select if action name is empty', async () => {
        Shopware.State.commit('swFlowState/setInvalidSequences', ['2']);

        const wrapper = createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture
            }
        });

        const actionSelection = wrapper.find('.sw-flow-sequence-action__selection-action');
        expect(actionSelection.classes()).toContain('has--error');
        expect(actionSelection.attributes('error')).toBeTruthy();
    });

    it('should remove error for after select an action name', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequenceFixture }]));
        Shopware.State.commit('swFlowState/setInvalidSequences', ['2']);

        let invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual(['2']);

        const wrapper = createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture
            }
        });

        const actionSelection = wrapper.find('.sw-flow-sequence-action__selection-action');
        expect(actionSelection.classes()).toContain('has--error');
        expect(actionSelection.attributes('error')).toBeTruthy();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(0).trigger('click');

        const modal = wrapper.find('.sw-flow-sequence-modal');
        await modal.trigger('click');

        invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual([]);
        expect(actionSelection.exists()).toBeFalsy();

        const addButton = wrapper.find('.sw-flow-sequence-action__add-button');
        expect(addButton.exists()).toBeTruthy();
    });

    it('should able to toggle add action button', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequenceFixture }]));

        const wrapper = createWrapper();

        let actionSelect = wrapper.find('.sw-single-select__selection');
        expect(actionSelect.exists()).toBeTruthy();

        const closeSelection = wrapper.find('.sw-icon[name="regular-times-circle-s"]');
        await closeSelection.trigger('click');

        let addButton = wrapper.find('.sw-flow-sequence-action__add-button');
        actionSelect = wrapper.find('.sw-single-select__selection');
        expect(addButton.exists()).toBeTruthy();
        expect(actionSelect.exists()).toBeFalsy();

        await addButton.trigger('click');

        addButton = wrapper.find('.sw-flow-sequence-action__add-button');
        actionSelect = wrapper.find('.sw-single-select__selection');
        expect(addButton.exists()).toBeFalsy();
        expect(actionSelect.exists()).toBeTruthy();
    });

    it('should able to disable add buttons', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: ACTION.ADD_TAG
            }
        });

        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__add-action').exists()).toBeTruthy();

        await wrapper.setProps({
            disabled: true
        });

        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__add-action').exists()).toBeFalsy();
    });

    it('should able to show move an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeTruthy();
    });

    it('should not able to show move an action if has only action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
            }
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeFalsy();
    });

    it('should not able to show move an action if has stop flow action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    id: '2',
                    actionName: ACTION.STOP_FLOW,
                    ruleId: null,
                    parentId: '1',
                    position: 1,
                    displayGroup: 1,
                    trueCase: true,
                },
            }
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeFalsy();
    });

    it('should able to show move down an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0]
                },
                3: {
                    ...sequencesFixture[1]
                }
            }
        });

        const moveDownAction = wrapper.find('.sw-flow-sequence-action__move-down');
        expect(moveDownAction.exists()).toBeTruthy();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState[0].position).toEqual(1);
        expect(sequencesState[1].position).toEqual(2);
        await moveDownAction.trigger('click');
        expect(sequencesState[0].position).toEqual(2);
        expect(sequencesState[1].position).toEqual(1);
    });

    it('should correct label in set order state description', async () => {
        Shopware.State.commit('swFlowState/setStateMachineState', [
            {
                technicalName: 'in_progress',
                stateMachine: {
                    technicalName: 'order_delivery.state'
                },
                translated: {
                    name: 'In progress'
                }
            },
            {
                technicalName: 'in_progress',
                stateMachine: {
                    technicalName: 'order.state'
                },
                translated: {
                    name: 'In Progress'
                }
            }
        ]);

        const wrapper = createWrapper({
            sequence: {
                id: '2',
                ruleId: null,
                parentId: '1',
                position: 1,
                displayGroup: 1,
                trueCase: false,
                config: {
                    order: 'in_progress'
                },
                actionName: ACTION.SET_ORDER_STATE
            }
        });

        const description = wrapper.find('.sw-flow-sequence-action__action-description');
        expect(description.text()).toContain('sw-flow.modals.status.labelOrderStatus: In Progress');
    });

    it('should has actions from app flow actions in actions list', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware']
            }
        ];

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');

        expect(actionItems.length).toEqual(5);
        expect(actionItems.at(0).get('.sw-highlight-text').text()).toBe('Telegram send message');
    });

    it('should disable the actions when inactive the app flow actions', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware'],
                app: {
                    active: false,
                    name: 'FlowAppSystem'
                }
            }
        ];

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const disabledAction = await wrapper.find('.sw-flow-sequence-action__disabled');
        expect(disabledAction.exists()).toBeTruthy();
    });

    it('should show the app action modal', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware']
            }
        ];

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(3).trigger('click');

        const modalElement = wrapper.find('.sw-flow-sequence-modal');
        expect(modalElement.exists()).toBeTruthy();
    });

    it('should group flow builder actions', async () => {
        const wrapper = createWrapper();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-grouped-single-select__group-separator');

        expect(actionItems.length).toEqual(2);
        expect(actionItems.at(0).text()).toEqual('sw-flow.actions.group.general');
        expect(actionItems.at(1).text()).toEqual('sw-flow.actions.group.tag');
    });

    it('should have tooltip for disabled actions', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware'],
                app: {
                    active: false,
                    name: 'FlowAppSystem'
                }
            }
        ];

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const disabledAction = wrapper.find('.sw-flow-sequence-action__disabled');
        expect(disabledAction.attributes()['tooltip-id']).toBeTruthy();
    });

    it('should correct actions label', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware'],
                app: {
                    active: false,
                    name: 'FlowAppSystem'
                },
                config: [
                    {
                        name: 'message',
                        label: {
                            'en-GB': 'Label',
                        },
                    }
                ]
            }
        ];

        const wrapper = await createWrapper({
            sequence: {
                id: '2',
                ruleId: null,
                parentId: '1',
                position: 1,
                displayGroup: 1,
                trueCase: false,
                config: {
                    message: 'message'
                },
                actionName: 'telegram.send.message'
            }
        }, appFlowResponse, 'appFlowAction');

        await wrapper.vm.$nextTick();
        const description = wrapper.find('.sw-flow-sequence-action__action-description');
        expect(description.exists()).toBeTruthy();
        expect(description.text()).toEqual('Label: message');
    });
});
