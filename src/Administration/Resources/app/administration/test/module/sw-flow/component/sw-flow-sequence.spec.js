import { shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-sequence';

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    config: {}
};

function createWrapper(propsData = {}) {
    return shallowMount(Shopware.Component.build('sw-flow-sequence'), {
        stubs: {
            'sw-flow-sequence-selector': true,
            'sw-flow-sequence-action': true,
            'sw-flow-sequence-condition': true
        },
        propsData: {
            sequence: sequenceFixture,
            ...propsData
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-flow/component/sw-flow-sequence', () => {
    it('should show sequence selector type correctly', async () => {
        const wrapper = createWrapper();
        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeTruthy();
        expect(action.exists()).toBeFalsy();
        expect(condition.exists()).toBeFalsy();
    });

    it('should show sequence condition type correctly', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111'
            }
        });

        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeFalsy();
        expect(action.exists()).toBeFalsy();
        expect(condition.exists()).toBeTruthy();
    });

    it('should show sequence action type correctly', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: 'sendEmail'
            }
        });

        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeFalsy();
        expect(action.exists()).toBeTruthy();
        expect(condition.exists()).toBeFalsy();
    });

    it('should show sequence action type correctly', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                actionName: 'sendEmail'
            }
        });

        const selector = wrapper.find('sw-flow-sequence-selector-stub');
        const action = wrapper.find('sw-flow-sequence-action-stub');
        const condition = wrapper.find('sw-flow-sequence-condition-stub');

        expect(selector.exists()).toBeFalsy();
        expect(action.exists()).toBeTruthy();
        expect(condition.exists()).toBeFalsy();
    });

    it('should show block children correctly', async () => {
        const wrapper = createWrapper({
            sequence: {
                ...sequenceFixture,
                ruleId: '1111',
                trueBlock: {
                    2: {
                        ...sequenceFixture,
                        parentId: '1',
                        trueCase: true
                    }
                },
                falseBlock: {
                    2: {
                        ...sequenceFixture,
                        parentId: '1',
                        trueCase: false
                    }
                }
            }
        });

        const trueBlock = wrapper.find('.sw-flow-sequence__true-block');
        const falseBlock = wrapper.find('.sw-flow-sequence__false-block');

        expect(trueBlock.exists()).toBeTruthy();
        expect(falseBlock.exists()).toBeTruthy();
    });
});
