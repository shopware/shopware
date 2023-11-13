import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-tree-node';

async function createWrapper(customProps = {}) {
    return shallowMount(await Shopware.Component.build('sw-condition-tree-node'), {
        stubs: {
            'sw-demo': true,
        },
        provide: {
            createCondition: () => {},
            insertNodeIntoTree: () => {},
            removeNodeFromTree: () => {},
            conditionDataProviderService: {
                getComponentByCondition: () => {
                    return 'sw-demo';
                },
            },
        },
        propsData: {
            level: 0,
            condition: {},
            ...customProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-tree-node', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled component node', async () => {
        const wrapper = await createWrapper();

        const demoComponent = wrapper.find('sw-demo-stub');

        expect(demoComponent.attributes().disabled).toBeUndefined();
    });

    it('should have disabled component node', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        const demoComponent = wrapper.find('sw-demo-stub');

        expect(demoComponent.attributes().disabled).toBe('true');
    });
});
