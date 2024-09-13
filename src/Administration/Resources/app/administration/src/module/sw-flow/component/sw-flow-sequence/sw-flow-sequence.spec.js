import { mount } from '@vue/test-utils';

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

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-flow-sequence', { sync: true }), {
        global: {
            stubs: {
                'sw-flow-sequence': await wrapTestComponent('sw-flow-sequence', { sync: true }),
                'sw-flow-sequence-selector': true,
                'sw-flow-sequence-action': true,
                'sw-flow-sequence-condition': true,
            },
        },
        props: {
            sequence: sequenceFixture,
            ...propsData,
        },
    });
}

describe('src/module/sw-flow/component/sw-flow-sequence', () => {
    it('should show sequence selector type correctly', async () => {
        const wrapper = await createWrapper();
        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeTruthy();
        expect(action.attributes('style')).toBe('display: none;');
        expect(condition.exists()).toBeFalsy();
    });

    it('should show sequence condition type correctly', async () => {
        const wrapper = await createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
            },
        });

        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeFalsy();
        expect(action.attributes('style')).toBe('display: none;');
        expect(condition.exists()).toBeTruthy();
    });

    it('should show sequence action type correctly', async () => {
        const wrapper = await createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: 'sendEmail',
            },
        });

        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeFalsy();
        expect(action.attributes('style')).not.toBe('display: none;');
        expect(condition.exists()).toBeFalsy();
    });

    it('should show block children correctly', async () => {
        const wrapper = await createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                trueBlock: {
                    2: {
                        ...sequenceFixture,
                        parentId: '1',
                        trueCase: true,
                    },
                },
                falseBlock: {
                    2: {
                        ...sequenceFixture,
                        parentId: '1',
                        trueCase: false,
                    },
                },
            },
        });

        const trueBlock = wrapper.find('.sw-flow-sequence__true-block');
        const falseBlock = wrapper.find('.sw-flow-sequence__false-block');

        expect(trueBlock.exists()).toBeTruthy();
        expect(falseBlock.exists()).toBeTruthy();
    });
});
