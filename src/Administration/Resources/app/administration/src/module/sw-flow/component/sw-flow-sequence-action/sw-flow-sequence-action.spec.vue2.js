import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swFlowSequenceAction from 'src/module/sw-flow/component/sw-flow-sequence-action';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-grouped-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';

import { ACTION } from 'src/module/sw-flow/constant/flow.constant';
import FlowBuilderService from 'src/module/sw-flow/service/flow-builder.service';

import EntityCollection from 'src/core/data/entity-collection.data';

import Vuex from 'vuex_v2';
import flowState from 'src/module/sw-flow/state/flow.state';

Shopware.Service().register('shopwareDiscountCampaignService', () => {
    return { isDiscountCampaignActive: jest.fn(() => true) };
});

Shopware.Service().register('flowBuilderService', () => {
    return new FlowBuilderService();
});

Shopware.Component.register('sw-flow-sequence-action', swFlowSequenceAction);

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
        tagIds: ['123'],
    },
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        actionName: ACTION.ADD_TAG,
    },
    {
        ...sequenceFixture,
        actionName: ACTION.MAIL_SEND,
        position: 2,
        id: '3',
    },
    {
        ...sequenceFixture,
        actionName: ACTION.MAIL_SEND,
        position: 3,
        id: '4',
    },
];

function getSequencesCollection(collection = []) {
    return new EntityCollection(
        '/flow_sequence',
        'flow_sequence',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null,
    );
}

async function createWrapper(propsData = {}, appFlowResponseData = [], flag = '') {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    const wrapper = shallowMount(await Shopware.Component.build('sw-flow-sequence-action'), {
        localVue,
        stubs: {
            'sw-icon': {
                template: '<div class="sw-icon" v-on="$listeners"></div>',
            },
            'sw-context-button': true,
            'sw-context-menu-item': {
                template: `
                    <div class="sw-context-menu-item" v-on="$listeners">
                      <slot></slot>
                    </div>
                `,
            },
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-grouped-single-select': await Shopware.Component.build('sw-grouped-single-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>',
            },
            'sw-select-result': {
                props: ['item', 'index'],
                template: `<li class="sw-select-result" @click.stop="onClickResult">
                                <slot></slot>
                           </li>`,
                methods: {
                    onClickResult() {
                        this.$parent.$parent.$emit('item-select', this.item);
                    },
                },
            },
            'sw-highlight-text': {
                props: ['text'],
                template: '<div class="sw-highlight-text">{{ this.text }}</div>',
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
                                tagIds: ['123'],
                            },
                        });
                    },
                },
            },
            'sw-flow-sequence-action-error': true,
        },
        propsData: {
            sequence: sequenceFixture,
            ...propsData,
        },

        provide: {
            repositoryFactory: {
                create: () => {
                    if (flag === 'appFlowAction') {
                        return {
                            create: () => {
                                return {};
                            },
                            search: () => Promise.resolve(appFlowResponseData),
                        };
                    }

                    return {
                        create: () => {
                            return {};
                        },
                        search: () => Promise.resolve([]),
                    };
                },
            },

            flowBuilderService: Shopware.Service('flowBuilderService'),
        },
    });
    wrapper.vm.$refs = {
        contextButton: [
            {
                $el: {
                    contains: () => true,
                },
            },
            {
                $el: {
                    contains: () => true,
                },
            },
        ],
    };

    return wrapper;
}

describe('src/module/sw-flow/component/sw-flow-sequence-action', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }]),
                },
                invalidSequences: [],
                mailTemplates: [],
                triggerEvent: {
                    data: {
                        customer: '',
                        order: '',
                    },
                    customerAware: true,
                    orderAware: true,
                    extensions: [],
                    mailAware: true,
                    name: 'checkout.customer.login',
                    aware: [
                        'Shopware\\Core\\Framework\\Event\\CustomerAware',
                        'Shopware\\Core\\Framework\\Event\\OrderAware',
                        'Shopware\\Core\\Framework\\Event\\MailAware',
                    ],
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
                ],
                appActions: [],
                originAvailableActions: [],
            },
        });
    });

    it('should able to add an action', async () => {
        const wrapper = await createWrapper();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(0).trigger('click');

        const modal = wrapper.find('.sw-flow-sequence-modal');
        await modal.trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];

        const newSequence = {
            ...wrapper.props().sequence,
            actionName: wrapper.vm.actionOptions[0].value,
        };

        expect(sequencesState[0]).toEqual(newSequence);

        const addActionSelect = wrapper.find('.sw-flow-sequence-action__select');
        expect(addActionSelect.exists()).toBeTruthy();
    });

    it('should show action list correctly', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
                3: {
                    ...sequencesFixture[1],
                },
            },
        });

        const actionItems = wrapper.findAll('.sw-flow-sequence-action__action-item');
        expect(actionItems).toHaveLength(2);
    });

    it('should show dynamic modal', async () => {
        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
                3: {
                    ...sequencesFixture[1],
                },
            },
        });

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(0).trigger('click');
        const modalElement = wrapper.find('.sw-flow-sequence-modal');

        expect(modalElement.exists()).toBeTruthy();
    });

    it('should not able to add more actions if existing action is stop flow', async () => {
        const wrapper = await createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: ACTION.STOP_FLOW,
            },
        });

        const addAction = wrapper.find('.sw-flow-sequence-action__add-action');
        expect(addAction.exists()).toBeFalsy();
    });

    it('should able to remove action container', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
                3: {
                    ...sequencesFixture[1],
                },
            },
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(3);


        const deleteContainer = wrapper.find('.sw-flow-sequence-action__delete-action-container');
        await deleteContainer.trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(1);
    });

    it('should able to remove an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
                3: {
                    ...sequencesFixture[1],
                },
            },
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(3);

        const deleteActions = wrapper.findAll('.sw-flow-sequence-action__delete-action');
        await deleteActions.at(0).trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(2);
        expect(sequencesState[0]).toEqual(sequencesFixture[0]);
    });

    it('should set error for single select if action name is empty', async () => {
        Shopware.State.commit('swFlowState/setInvalidSequences', ['2']);

        const wrapper = await createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture,
            },
        });

        const actionSelection = wrapper.find('.sw-flow-sequence-action__selection-action');
        expect(actionSelection.classes()).toContain('has--error');
        expect(actionSelection.attributes('error')).toBeTruthy();
    });

    it('should remove error for after select an action name', async () => {
        Shopware.State.commit(
            'swFlowState/setSequences',
            getSequencesCollection([{ ...sequenceFixture }]),
        );
        Shopware.State.commit('swFlowState/setInvalidSequences', ['2']);

        let invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual(['2']);

        const wrapper = await createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture,
            },
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
        expect(actionSelection.exists()).toBeTruthy();
    });

    it('should able to disable add buttons', async () => {
        const wrapper = await createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: ACTION.ADD_TAG,
            },
        });

        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__select').exists()).toBeTruthy();

        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__context-button').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__add-action').exists()).toBeFalsy();
    });

    it('should able to show move an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                    position: 5,
                },
                3: {
                    ...sequencesFixture[1],
                    position: 6,
                },
                4: {
                    ...sequencesFixture[2],
                    position: 7,
                },
            },
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeTruthy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeTruthy();
    });

    it('should not able to show move an action if has only action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
            },
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeFalsy();
    });

    it('should not able to show move an action if has stop flow action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
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
            },
        });

        expect(wrapper.find('.sw-flow-sequence-action__move-down').exists()).toBeFalsy();
        expect(wrapper.find('.sw-flow-sequence-action__move-up').exists()).toBeFalsy();
    });

    it('should able to show move down an action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                    position: 5,
                },
                3: {
                    ...sequencesFixture[1],
                    position: 6,
                },
                4: {
                    ...sequencesFixture[2],
                    position: 7,
                },
            },
        });

        const moveDownAction = wrapper.find('.sw-flow-sequence-action__move-down');
        expect(moveDownAction.exists()).toBeTruthy();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState[0].position).toBe(1);
        expect(sequencesState[1].position).toBe(2);
        await moveDownAction.trigger('click');
        expect(sequencesState[0].position).toBe(1);
        expect(sequencesState[1].position).toBe(7);
    });

    it('should reset position after deleting action', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper({
            sequence: {
                2: {
                    ...sequencesFixture[0],
                },
                3: {
                    ...sequencesFixture[1],
                },
            },
        });

        // delete the first action with position 1
        const deleteActions = wrapper.findAll('.sw-flow-sequence-action__delete-action');
        await deleteActions.at(0).trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(2);
        expect(sequencesState[0].position).toBe(1);
    });

    it('should correct label in set order state description', async () => {
        Shopware.State.commit('swFlowState/setStateMachineState', [
            {
                technicalName: 'in_progress',
                stateMachine: {
                    technicalName: 'order_delivery.state',
                },
                translated: {
                    name: 'In progress',
                },
            },
            {
                technicalName: 'in_progress',
                stateMachine: {
                    technicalName: 'order.state',
                },
                translated: {
                    name: 'In Progress',
                },
            },
        ]);

        const wrapper = await createWrapper({
            sequence: {
                id: '2',
                ruleId: null,
                parentId: '1',
                position: 1,
                displayGroup: 1,
                trueCase: false,
                config: {
                    order: 'in_progress',
                },
                actionName: ACTION.SET_ORDER_STATE,
            },
        });

        const description = wrapper.find('.sw-flow-sequence-action__action-description');
        expect(description.text()).toContain('sw-flow.modals.status.labelOrderStatus: In Progress');
    });

    it('should group flow builder actions', async () => {
        const wrapper = await createWrapper();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = await wrapper.findAll('.sw-grouped-single-select__group-separator');

        expect(actionItems).toHaveLength(2);
        expect(actionItems.at(0).text()).toBe('sw-flow.actions.group.general');
        expect(actionItems.at(1).text()).toBe('sw-flow.actions.group.tag');
    });

    it('should has actions from app flow actions in actions list', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware'],
            },
        ];

        Shopware.State.commit('swFlowState/setAppActions', [{
            label: 'Telegram send message',
            name: 'telegram.send.message',
            swIcon: 'default-communication-speech-bubbles',
            requirements: ['customerAware', 'orderAware'],
        }]);

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');
        await wrapper.vm.$nextTick();

        const actionItems = wrapper.findAll('.sw-select-result');

        expect(actionItems).toHaveLength(6);
        expect(actionItems.at(5).get('.sw-highlight-text').text()).toBe('Telegram send message');
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
                    name: 'FlowAppSystem',
                },
            },
        ];

        Shopware.State.commit('swFlowState/setAppActions', [{
            name: 'telegram.send.message',
        }]);

        const wrapper = await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');
        await wrapper.vm.$nextTick();

        const disabledAction = await wrapper.find('.sw-flow-sequence-action__disabled');
        expect(disabledAction.exists()).toBeTruthy();
    });

    it('should show the app action modal', async () => {
        const appFlowResponse = [
            {
                label: 'Telegram send message',
                name: 'telegram.send.message',
                swIcon: 'default-communication-speech-bubbles',
                requirements: ['customerAware', 'orderAware'],
            },
        ];

        const wrapper = await await createWrapper({}, appFlowResponse, 'appFlowAction');
        await wrapper.vm.$nextTick();

        const actionSelect = wrapper.find('.sw-single-select__selection');
        await actionSelect.trigger('click');

        const actionItems = wrapper.findAll('.sw-select-result');
        await actionItems.at(3).trigger('click');

        const modalElement = wrapper.find('.sw-flow-sequence-modal');
        expect(modalElement.exists()).toBeTruthy();
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
                    name: 'FlowAppSystem',
                },
            },
        ];

        const wrapper = await await createWrapper({}, appFlowResponse, 'appFlowAction');
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
                    name: 'FlowAppSystem',
                },
                config: [
                    {
                        name: 'message',
                        label: {
                            'en-GB': 'Label',
                        },
                    },
                ],
            },
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
                    message: 'message',
                },
                actionName: 'telegram.send.message',
            },
        }, appFlowResponse, 'appFlowAction');

        await wrapper.vm.$nextTick();
        const description = wrapper.find('.sw-flow-sequence-action__action-description');
        expect(description.exists()).toBeTruthy();
        expect(description.text()).toBe('message: message');
    });
});
