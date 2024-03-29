import { mount } from '@vue/test-utils';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/base/sw-label';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-select-selection-list', { sync: true }), {
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

        const element = wrapper.find('sw-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBeTruthy();
    });

    it('should render labels which are not dismissable', async () => {
        const wrapper = await createWrapper({
            disabled: true,
            selections: [{ label: 'Selection1' }],
        });

        const element = wrapper.find('sw-label');
        expect(element.exists()).toBeTruthy();
        expect(element.attributes().dismissable).toBeFalsy();
    });
});
