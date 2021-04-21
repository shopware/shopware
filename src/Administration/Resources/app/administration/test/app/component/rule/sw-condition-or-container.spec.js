import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-or-container';

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-or-container'), {
        stubs: {
            'sw-button': true,
            'sw-condition-tree-node': true
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {},
                getAndContainerData: () => {}
            },
            createCondition: () => {},
            insertNodeTree: {},
            insertNodeIntoTree: () => {},
            removeNodeFromTree: {},
            childAssociationField: 'test'
        },
        propsData: {
            condition: {
                test: [
                    {},
                    {}
                ]
            },
            level: 0,
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-or-container', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
        buttons.wrappers.forEach(button => {
            expect(button.attributes().disabled).toBeUndefined();
        });
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');
        const buttons = wrapper.findAll('sw-button-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
        buttons.wrappers.forEach(button => {
            expect(button.attributes().disabled).toBe('true');
        });
    });
});
