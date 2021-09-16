import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/view/detail/sw-flow-detail-flow';
import 'src/module/sw-flow/component/sw-flow-sequence';
import 'src/module/sw-flow/component/sw-flow-trigger';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    config: {}
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        ruleId: '1111'
    },
    {
        ...sequenceFixture,
        ruleId: '2222',
        parentId: '1',
        id: '2',
        trueCase: true
    },
    {
        ...sequenceFixture,
        actionName: 'sendMail',
        parentId: '1',
        id: '3',
        trueCase: false
    },
    {
        ...sequenceFixture,
        actionName: 'addTag',
        parentId: '1',
        id: '4',
        position: 2,
        trueCase: false
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '5'
    }
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
                trueCase: true
            }
        },
        falseBlock: {
            3: {
                ...sequenceFixture,
                actionName: 'sendMail',
                parentId: '1',
                id: '3',
                trueCase: false
            },
            4: {
                ...sequenceFixture,
                actionName: 'addTag',
                parentId: '1',
                position: 2,
                id: '4',
                trueCase: false
            }
        }
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '5'
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

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-detail-flow'), {
        localVue,
        provide: {
            repositoryFactory: {
                create: () => ({
                    create: () => {
                        return {};
                    },
                    get: (id) => Promise.resolve({
                        id,
                        name: 'Rule name',
                        description: 'Rule description'
                    })
                })
            },

            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            flowActionService: {
                getActions: jest.fn(() => {
                    return Promise.resolve([]);
                })
            }
        },

        stubs: {
            'sw-icon': {
                template: '<div class="sw-icon" v-on="$listeners"></div>'
            },
            'sw-flow-sequence': Shopware.Component.build('sw-flow-sequence'),
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
                `
            }
        }
    });
}

enableAutoDestroy(afterEach);

describe('module/sw-flow/view/detail/sw-flow-detail-flow', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    name: 'Flow 1',
                    eventName: '',
                    sequences: getSequencesCollection()
                },
                invalidSequences: []
            }
        });
    });

    it('should show create an selector when select initially', async () => {
        const wrapper = createWrapper([
            'flow.editor'
        ]);

        let helpElement = wrapper.find('.sw-flow-detail-flow__trigger-explain');
        let flowDiagram = wrapper.find('.sw-flow-detail-flow__sequence-diagram');

        expect(helpElement.exists()).toBeTruthy();
        expect(flowDiagram.exists()).toBeFalsy();

        const triggerInput = wrapper.find('.sw-flow-trigger');
        await triggerInput.setValue('checkout.customer');
        await triggerInput.trigger('input');

        helpElement = wrapper.find('.sw-flow-detail-flow__trigger-explain');
        flowDiagram = wrapper.find('.sw-flow-detail-flow__sequence-diagram');
        const selectorSequence = flowDiagram.find('sw-flow-sequence-selector-stub');

        expect(helpElement.exists()).toBeFalsy();
        expect(flowDiagram.exists()).toBeTruthy();
        expect(selectorSequence.exists()).toBeTruthy();
    });

    it('should render flow correctly', async () => {
        Shopware.State.commit('swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture)
            });

        const wrapper = createWrapper([
            'flow.editor'
        ]);

        const sequences = wrapper.findAll('.sw-flow-sequence');
        expect(sequences.length).toEqual(4);
        expect(wrapper.vm.formatSequences).toEqual(formatSequences);

        // Based on sequences, there are 2 rootSequences
        const rootSequences = wrapper.findAll('.sw-flow-detail-flow__sequences');
        expect(rootSequences.length).toEqual(2);
    });

    it('should able to add new sequence', async () => {
        Shopware.State.commit('swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture)
            });

        const wrapper = createWrapper([
            'flow.editor'
        ]);

        const addButton = wrapper.find('.sw-flow-detail-flow__position-plus .sw-icon');
        await addButton.trigger('click');

        const sequences = wrapper.findAll('.sw-flow-sequence');
        expect(sequences.length).toEqual(5);
        const selectorSequence = sequences.at(4).find('sw-flow-sequence-selector-stub');
        expect(selectorSequence.exists()).toBeTruthy();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(6);
        expect(sequencesState[sequencesState.length - 1].displayGroup).toEqual(3);
        expect(sequencesState[sequencesState.length - 1].position).toEqual(1);
        expect(sequencesState[sequencesState.length - 1].parentId).toBeNull();
    });
});
