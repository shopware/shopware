import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-and-container';

function createWrapper(customProps = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-condition-and-container'), {
        localVue,
        stubs: {
            'sw-button': true,
            'sw-condition-tree-node': true
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {}
            },
            createCondition: () => {},
            insertNodeTree: {},
            insertNodeIntoTree: () => {},
            removeNodeFromTree: {},
            childAssociationField: 'test',
            acl: {
                can: () => true
            }
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            condition: {
                foo: {},
                test: {
                    foo: 'bar'
                }
            },
            level: 0,
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-and-container', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled condition tree', async () => {
        const wrapper = createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition tree', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });

    it('should have enabled buttons', async () => {
        const wrapper = createWrapper();

        const buttons = wrapper.findAll('sw-button-stub');

        buttons.wrappers.forEach(button => {
            expect(button.attributes().disabled).toBeUndefined();
        });
    });

    it('should have enabled buttons', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const buttons = wrapper.findAll('sw-button-stub');

        buttons.wrappers.forEach(button => {
            expect(button.attributes().disabled).toBe('true');
        });
    });
});
