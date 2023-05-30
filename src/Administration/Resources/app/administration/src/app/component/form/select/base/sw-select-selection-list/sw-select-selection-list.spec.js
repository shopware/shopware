import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-select-selection-list';

async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-select-selection-list'), {
        stubs: {
            'sw-label': {
                template: '<div class="sw-label"><slot></slot></div>',
            },
        },
        propsData: {
            ...propsData,
        },
    });
}

describe('src/app/component/form/select/base/sw-select-selection-list', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render dismissable labels', async () => {
        const wrapper = await createWrapper({
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.sw-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBeTruthy();
    });

    it('should render labels which are not dismissable', async () => {
        const wrapper = await createWrapper({
            disabled: true,
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('.sw-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBeFalsy();
    });
});
