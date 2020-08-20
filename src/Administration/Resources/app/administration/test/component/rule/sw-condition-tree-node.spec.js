import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-tree-node';

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-tree-node'), {
        stubs: {
            'sw-demo': true
        },
        provide: {
            createCondition: () => {},
            insertNodeIntoTree: () => {},
            removeNodeFromTree: () => {},
            conditionDataProviderService: {
                getComponentByCondition: () => {
                    return 'sw-demo';
                }
            }
        },
        propsData: {
            level: 0,
            condition: {},
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-tree-node', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have enabled component node', () => {
        const wrapper = createWrapper();

        const demoComponent = wrapper.find('sw-demo-stub');

        expect(demoComponent.attributes().disabled).toBeUndefined();
    });

    it('should have disabled component node', () => {
        const wrapper = createWrapper();
        wrapper.setProps({
            disabled: true
        });

        const demoComponent = wrapper.find('sw-demo-stub');

        expect(demoComponent.attributes().disabled).toBe('true');
    });
});
