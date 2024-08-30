import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package services-settings
 */

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    config: {},
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        ruleId: '1111',
    },
    {
        ...sequenceFixture,
        ruleId: '2222',
        parentId: '1',
        id: '2',
        trueCase: true,
    },
    {
        ...sequenceFixture,
        actionName: 'sendMail',
        parentId: '1',
        id: '3',
        trueCase: false,
    },
    {
        ...sequenceFixture,
        actionName: 'addTag',
        parentId: '1',
        id: '4',
        position: 2,
        trueCase: false,
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '5',
    },
];

const formatSequences = [
    {
        ...sequenceFixture,
        ruleId: '1111',
        trueBlock: {
            2: {
                ...sequenceFixture,
                ruleId: '2222',
                parentId: '1',
                id: '2',
                trueCase: true,
            },
        },
        falseBlock: {
            3: {
                ...sequenceFixture,
                actionName: 'sendMail',
                parentId: '1',
                id: '3',
                trueCase: false,
            },
            4: {
                ...sequenceFixture,
                actionName: 'addTag',
                parentId: '1',
                position: 2,
                id: '4',
                trueCase: false,
            },
        },
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '5',
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

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-flow-detail-flow', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': {
                    template: '<div class="sw-icon"></div>',
                },
                'sw-flow-sequence': await wrapTestComponent('sw-flow-sequence'),
                'sw-flow-sequence-selector': true,
                'sw-flow-sequence-action': true,
                'sw-flow-sequence-condition': true,
                'sw-button': true,
                'sw-label': true,
                'sw-loader': true,
                'sw-flow-trigger': {
                    props: ['eventName'],
                    template: `
                    <input
                        :value="eventName"
                        @input="$emit('option-select', $event.target.value)"
                        class="sw-flow-trigger" />
                `,
                },
                'sw-alert': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => {
                            return {};
                        },
                        get: (id) => Promise.resolve({
                            id,
                            name: 'Rule name',
                            description: 'Rule description',
                        }),
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                flowActionService: {
                    getActions: jest.fn(() => {
                        return Promise.resolve([]);
                    }),
                },
                ruleConditionDataProviderService: {
                    getRestrictedRules: () => Promise.resolve([]),
                },
            },
        },
    });
}

describe('module/sw-flow/view/detail/sw-flow-detail-flow', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    name: 'Flow 1',
                    eventName: '',
                    sequences: getSequencesCollection(),
                },
                invalidSequences: [],
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
                originAvailableActions: [],
            },
        });
    });

    it('should show create an selector when select initially', async () => {
        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        let helpElement = wrapper.find('.sw-flow-detail-flow__trigger-explain');
        let flowDiagram = wrapper.find('.sw-flow-detail-flow__sequence-diagram');

        expect(helpElement.exists()).toBeTruthy();
        expect(flowDiagram.exists()).toBeFalsy();

        const triggerInput = wrapper.find('.sw-flow-trigger');
        await triggerInput.setValue('checkout.customer');
        await triggerInput.trigger('input');
        await flushPromises();

        helpElement = wrapper.find('.sw-flow-detail-flow__trigger-explain');
        flowDiagram = wrapper.find('.sw-flow-detail-flow__sequence-diagram');
        const selectorSequence = flowDiagram.find('sw-flow-sequence-selector-stub');

        expect(helpElement.exists()).toBeFalsy();
        expect(flowDiagram.exists()).toBeTruthy();
        expect(selectorSequence.exists()).toBeTruthy();
    });

    it('should render flow correctly', async () => {
        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture),
            },
        );

        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        const sequences = wrapper.findAll('.sw-flow-sequence');
        expect(sequences).toHaveLength(4);
        expect(wrapper.vm.formatSequences).toEqual(formatSequences);

        // Based on sequences, there are 2 rootSequences
        const rootSequences = wrapper.findAll('.sw-flow-detail-flow__sequences');
        expect(rootSequences).toHaveLength(2);
    });

    it('should able to add new sequence', async () => {
        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture),
            },
        );

        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        const addButton = wrapper.find('.sw-flow-detail-flow__position-plus .sw-icon');
        await addButton.trigger('click');

        const sequences = wrapper.findAll('.sw-flow-sequence');
        expect(sequences).toHaveLength(5);
        const selectorSequence = sequences.at(4).find('sw-flow-sequence-selector-stub');
        expect(selectorSequence.exists()).toBeTruthy();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(6);
        expect(sequencesState[sequencesState.length - 1].displayGroup).toBe(3);
        expect(sequencesState[sequencesState.length - 1].position).toBe(1);
        expect(sequencesState[sequencesState.length - 1].parentId).toBeNull();
    });

    it('should be able to show warning alert when has invalid action', async () => {
        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: [{
                    id: '1',
                    actionName: 'action.something.name',
                    ruleId: null,
                    parentId: null,
                    position: 1,
                    displayGroup: 1,
                    config: {},
                }],
            },
        );

        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        const alertElement = wrapper.findAll('.sw-flow-detail-flow__warning-box');
        expect(alertElement).toHaveLength(1);
    });

    it('should not able to edit flow template', async () => {
        Shopware.State.commit('swFlowState/setFlow', {
            eventName: 'checkout.customer',
            name: 'Flow 1',
            sequences: [{
                id: '1',
                actionName: 'action.something.name',
                ruleId: null,
                parentId: null,
                position: 1,
                displayGroup: 1,
                config: {},
            }],
        });

        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        await wrapper.setProps({ isTemplate: true });

        const alertElement = wrapper.findAll('.sw-flow-detail-flow-template');
        expect(alertElement).toHaveLength(1);
    });

    it('should create a sequence when sequences is empty and eventName exists', async () => {
        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        Shopware.State.commit('swFlowState/setFlow', {
            eventName: 'checkout.customer',
            name: 'Flow 1',
            sequences: [],
        });

        const createSequenceMock = jest.spyOn(wrapper.vm, 'createSequence');
        await flushPromises();

        expect(createSequenceMock).toHaveBeenCalledTimes(1);
    });
});
