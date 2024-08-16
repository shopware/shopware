import { mount } from '@vue/test-utils';

import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 * @group disabledCompat
 */

const sequences = [
    {
        id: '1',
        actionName: null,
        ruleId: '1111',
        parentId: null,
        position: 1,
        displayGroup: 1,
    },
    {
        id: '2',
        actionName: null,
        ruleId: null,
        parentId: '1',
        position: 1,
        displayGroup: 1,
        trueCase: true,
    },
    {
        id: '3',
        actionName: null,
        ruleId: null,
        parentId: '1',
        position: 1,
        displayGroup: 1,
        trueCase: false,
    },
];

async function createWrapper() {
    return mount(await wrapTestComponent('sw-flow-sequence-selector', {
        sync: true,
    }), {
        props: {
            sequence: sequences[0],
        },

        global: {
            stubs: {
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-icon': true,
                'router-link': true,
                'sw-loader': true,
            },
        },
    });
}

describe('src/module/sw-flow/component/sw-flow-sequence-selector', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences,
                },
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should update data correctly when adding a condition', async () => {
        await wrapper.setProps({
            sequence: sequences[1],
        });

        const button = wrapper.find('.sw-flow-sequence-selector__add-condition');
        await button.trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        const sequence = {
            ...wrapper.props().sequence,
            ruleId: '',
        };

        expect(sequencesState[1]).toEqual(sequence);
    });

    it('should update data correctly when adding an action', async () => {
        await wrapper.setProps({
            sequence: sequences[2],
        });

        const button = wrapper.find('.sw-flow-sequence-selector__add-action');
        await button.trigger('click');

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        const sequence = {
            ...wrapper.props().sequence,
            actionName: '',
        };

        expect(sequencesState[2]).toEqual(sequence);
    });

    it('should show title correctly', async () => {
        const title = wrapper.find('.sw-flow-sequence-selector__title');

        expect(title.text()).toBe('sw-flow.detail.sequence.selectorTitle');

        await wrapper.setProps({
            sequence: {
                ...sequences[0],
                position: 2,
                displayGroup: 2,
            },
        });

        expect(title.text()).toBe('sw-flow.detail.sequence.selectorTitleAddSequence');
    });

    it('should show help text correctly', async () => {
        const helpText = wrapper.find('.sw-flow-sequence-selector__help-text');
        expect(helpText.text()).toBe('sw-flow.detail.sequence.selectorHelpText');

        await wrapper.setProps({
            sequence: {
                ...sequences[0],
                position: 2,
                displayGroup: 2,
            },
        });

        expect(helpText.text()).toBe('sw-flow.detail.sequence.selectorHelpTextAddSequence');

        await wrapper.setProps({
            sequence: sequences[1],
        });

        expect(helpText.text()).toBe('sw-flow.detail.sequence.selectorHelpTextTrueCondition');

        await wrapper.setProps({
            sequence: sequences[2],
        });


        expect(helpText.text()).toBe('sw-flow.detail.sequence.selectorHelpTextFalseCondition');
    });

    it('should able to disable add buttons', async () => {
        const addCondition = wrapper.find('.sw-flow-sequence-selector__add-condition');
        const addAction = wrapper.find('.sw-flow-sequence-selector__add-action');

        expect(addCondition.attributes().disabled).toBeFalsy();
        expect(addAction.attributes().disabled).toBeFalsy();

        await wrapper.setProps({
            disabled: true,
        });

        expect(addCondition.classes()).toContain('sw-button--disabled');
        expect(addAction.classes()).toContain('sw-button--disabled');
    });
});
