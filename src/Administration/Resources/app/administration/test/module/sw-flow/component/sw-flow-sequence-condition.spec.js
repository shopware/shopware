import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-sequence-condition';

import EntityCollection from 'src/core/data/entity-collection.data';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: '',
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
        id: '2',
        parentId: '1',
        trueCase: true
    },
    {
        ...sequenceFixture,
        id: '3',
        parentId: '1',
        trueCase: false
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

function createWrapper(propsData = {}) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-sequence-condition'), {
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
            'sw-entity-single-select': {
                props: ['value'],
                template: `
                    <div class="sw-entity-single-select">
                        <input
                            class="sw-entity-single-select__selection-input"
                            :value="value"
                            @input="$emit('change', $event.target.value, { name: 'Rule name', id: $event.target.value })"
                        />
                        <slot name="before-item-list"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-label': true,
            'sw-flow-rule-modal': true
        },
        propsData: {
            sequence: sequenceFixture,
            ...propsData
        },
        provide: {
            flowBuilderService: {
                getActionModalName: () => {}
            },
            repositoryFactory: {
                create: () => {
                    return {
                        search: jest.fn(() => {
                            return Promise.resolve([]);
                        }),
                        get: (id) => Promise.resolve({
                            id,
                            name: 'Rule name'
                        }),
                        create: () => { return {}; }
                    };
                }
            }
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-flow/component/sw-flow-sequence-condition', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }])
                },
                invalidSequences: []
            }
        });
    });

    it('should show help element if sequence is a first created root sequence', async () => {
        const wrapper = createWrapper();

        const helpElement = wrapper.find('.sw-flow-sequence-condition__explains');
        expect(helpElement.exists()).toBeTruthy();

        const trueArrow = wrapper.find('.sw-flow-sequence-condition__true-arrow');
        expect(trueArrow.exists()).toBeFalsy();

        const falseArrow = wrapper.find('.sw-flow-sequence-condition__false-arrow');
        expect(falseArrow.exists()).toBeFalsy();
    });

    it('should create 2 true/false children selectors if sequence is root sequence which contains a rule', async () => {
        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(1);

        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                }
            }
        });

        const helpElement = wrapper.find('.sw-flow-sequence-condition__explains');
        expect(helpElement.exists()).toBeFalsy();

        const trueArrow = wrapper.find('.sw-flow-sequence-condition__true-arrow');
        expect(trueArrow.exists()).toBeTruthy();

        const falseArrow = wrapper.find('.sw-flow-sequence-condition__false-arrow');
        expect(falseArrow.exists()).toBeTruthy();

        // Flow sequences add 2 new selectors
        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(3);

        // Show context button
        const trueAction = wrapper.find('.sw-flow-sequence-condition__true-action');
        expect(trueAction.exists()).toBeTruthy();

        const falseAction = wrapper.find('.sw-flow-sequence-condition__false-action');
        expect(falseAction.exists()).toBeTruthy();

        const falseArrowIcon = wrapper.find('.sw-icon[name="small-arrow-large-down"]');
        expect(falseArrowIcon.exists()).toBeFalsy();

        const trueArrowIcon = wrapper.find('.sw-icon[name="small-arrow-large-right"]');
        expect(trueArrowIcon.exists()).toBeFalsy();
    });

    it('should show arrow icon if sequence has trueBlock or falseBlock', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                trueBlock: {
                    2: {
                        ...sequencesFixture[1]
                    }
                },
                falseBlock: {
                    3: {
                        ...sequencesFixture[2]
                    }
                },
                rule: {
                    name: 'Rule name',
                    id: '1111'
                }
            }
        });

        // Show context button
        const trueAction = wrapper.find('.sw-flow-sequence-condition__true-action');
        expect(trueAction.exists()).toBeFalsy();

        const falseAction = wrapper.find('.sw-flow-sequence-condition__false-action');
        expect(falseAction.exists()).toBeFalsy();

        const falseArrowIcon = wrapper.find('.sw-icon[name="small-arrow-large-down"]');
        expect(falseArrowIcon.exists()).toBeTruthy();

        const trueArrowIcon = wrapper.find('.sw-icon[name="small-arrow-large-right"]');
        expect(trueArrowIcon.exists()).toBeTruthy();
    });

    it('should able to add new trueBlock or falseBlock', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{
                ...sequenceFixture,
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                }
            }]));

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(1);

        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                parentId: '4',
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                }
            }
        });

        // Show context button
        const conditionTrueBlock = wrapper.findAll('.sw-flow-sequence-condition__true-action .sw-context-menu-item');
        await conditionTrueBlock.at(0).trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(2);

        const actionFalseBlock = wrapper.findAll('.sw-flow-sequence-condition__false-action .sw-context-menu-item');
        await actionFalseBlock.at(1).trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(3);
    });

    it('should set error for single select if action name is empty', async () => {
        Shopware.State.commit('swFlowState/setInvalidSequences', ['1']);

        const wrapper = createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture
            }
        });

        const actionSelection = wrapper.find('.sw-flow-sequence-condition__selection-rule');
        expect(actionSelection.attributes('error')).toBeTruthy();
    });

    it('should remove error for after select an action name', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequenceFixture }]));
        Shopware.State.commit('swFlowState/setInvalidSequences', ['1']);

        let invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual(['1']);

        const wrapper = createWrapper();
        await wrapper.setProps({
            sequence: {
                ...sequenceFixture
            }
        });

        const actionSelection = wrapper.find('.sw-flow-sequence-condition__selection-rule');
        expect(actionSelection.attributes('error')).toBeTruthy();

        const ruleSelect = wrapper.find('.sw-entity-single-select__selection-input');
        await ruleSelect.setValue('1');
        await ruleSelect.trigger('input');

        invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual([]);
    });

    it('should able to toggle add action button', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequenceFixture }]));

        const wrapper = createWrapper();

        let ruleSelect = wrapper.find('.sw-flow-sequence-condition__selection-rule');
        expect(ruleSelect.exists()).toBeTruthy();

        const closeSelection = wrapper.find('.sw-icon[name="small-default-x-circle"]');
        await closeSelection.trigger('click');

        let addButton = wrapper.find('.sw-flow-sequence-condition__add-button');
        ruleSelect = wrapper.find('.sw-flow-sequence-condition__selection-rule');
        expect(addButton.exists()).toBeTruthy();
        expect(ruleSelect.exists()).toBeFalsy();

        await addButton.trigger('click');

        addButton = wrapper.find('.sw-flow-sequence-condition__add-button');
        ruleSelect = wrapper.find('.sw-flow-sequence-condition__selection-rule');
        expect(addButton.exists()).toBeFalsy();
        expect(ruleSelect.exists()).toBeTruthy();
    });

    it('should able to remove a condition and its children', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                },
                trueBlock: {
                    2: {
                        ...sequencesFixture[1],
                        _isNew: true
                    }
                },
                falseBlock: {
                    3: {
                        ...sequencesFixture[2],
                        _isNew: true
                    }
                }
            }
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(3);


        const deleteRule = wrapper.findAll('.sw-flow-sequence-condition__delete-condition');
        await deleteRule.trigger('click');
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(0);
    });

    it('should able to remove a condition and its children', async () => {
        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                },
                trueBlock: {
                    2: {
                        ...sequencesFixture[1],
                        _isNew: true
                    }
                },
                falseBlock: {
                    3: {
                        ...sequencesFixture[2],
                        _isNew: true
                    }
                }
            }
        });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(3);


        const deleteRule = wrapper.findAll('.sw-flow-sequence-condition__delete-condition');
        await deleteRule.trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(0);
    });

    it('should able to change rule', async () => {
        const sequence = {
            ...sequenceFixture,
            ruleId: '1111',
            rule: {
                name: 'Rule name',
                id: '1111'
            }
        };

        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequence }]));

        const wrapper = createWrapper({
            sequence
        });

        const editButton = wrapper.find('.sw-flow-sequence-condition__rule-change');
        await editButton.trigger('click');

        const ruleSelect = wrapper.find('.sw-entity-single-select__selection-input');
        await ruleSelect.setValue('2222');
        await ruleSelect.trigger('input');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState[0]).toEqual({
            ...sequence,
            ruleId: '2222',
            rule: {
                ...sequence.rule,
                id: '2222'
            }
        });
    });

    it('should able to delete rule', async () => {
        const sequence = {
            ...sequenceFixture,
            ruleId: '1111',
            rule: {
                name: 'Rule name',
                id: '1111'
            }
        };

        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequence }]));

        const wrapper = createWrapper({
            sequence
        });

        const editButton = wrapper.find('.sw-flow-sequence-condition__rule-delete');
        await editButton.trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState[0]).toEqual({
            ...sequence,
            rule: null,
            ruleId: ''
        });
    });

    it('should able to disable add buttons', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                rule: {
                    name: 'Rule name',
                    id: '1111'
                }
            }
        });

        const components = [
            '.sw-flow-sequence-condition__context-button',
            '.sw-flow-sequence-condition__rule-context-button',
            '.sw-flow-sequence-condition__add-false-action',
            '.sw-flow-sequence-condition__add-false-condition',
            '.sw-flow-sequence-condition__add-true-action',
            '.sw-flow-sequence-condition__add-true-condition'
        ];

        components.forEach(component => {
            expect(wrapper.find(component).attributes().disabled).toBeFalsy();
        });

        await wrapper.setProps({
            disabled: true
        });


        components.forEach(component => {
            expect(wrapper.find(component).attributes().disabled).toBeTruthy();
        });
    });

    it('should show rule modal when click on create new rule option', async () => {
        const sequence = {
            ...sequenceFixture,
            ruleId: ''
        };

        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequence }]));
        const wrapper = createWrapper({
            sequence
        });

        let createRuleModal = wrapper.find('sw-flow-rule-modal-stub');
        expect(createRuleModal.exists()).toBeFalsy();

        const createRuleButton = wrapper.find('.sw-select-result__create-new-rule');
        await createRuleButton.trigger('click');

        createRuleModal = wrapper.find('sw-flow-rule-modal-stub');
        expect(createRuleModal.exists()).toBeTruthy();
    });

    it('should show rule modal when click on edit rule option', async () => {
        const sequence = {
            ...sequenceFixture,
            ruleId: '1111',
            rule: {
                name: 'Rule name',
                id: '1111'
            }
        };

        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection([{ ...sequence }]));

        const wrapper = createWrapper({
            sequence
        });

        let ruleModal = wrapper.find('sw-flow-rule-modal-stub');
        expect(ruleModal.exists()).toBeFalsy();

        const editButton = wrapper.find('.sw-flow-sequence-condition__rule-edit');
        await editButton.trigger('click');

        ruleModal = wrapper.find('sw-flow-rule-modal-stub');
        expect(ruleModal.exists()).toBeTruthy();
        expect(ruleModal.attributes()['rule-id']).toEqual('1111');
    });
});
