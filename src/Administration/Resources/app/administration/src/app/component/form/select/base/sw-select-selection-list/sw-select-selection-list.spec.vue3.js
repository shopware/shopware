import { mount } from '@vue/test-utils_v3';
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

    it('should show only five tags of selection list', async () => {
        const wrapper = await createWrapper({
            selections: [
                { label: 'Selection1' },
                { label: 'Selection2' },
                { label: 'Selection3' },
                { label: 'Selection4' },
                { label: 'Selection5' },
                { label: 'Selection6' },
            ],
        });

        expect(wrapper.vm.visibleTags).toEqual([
            { label: 'Selection1' },
            { label: 'Selection2' },
            { label: 'Selection3' },
            { label: 'Selection4' },
            { label: 'Selection5' },
        ]);
    });

    it('should show number of hidden tag', async () => {
        const wrapper = await createWrapper({
            selections: [
                { label: 'Selection1' },
                { label: 'Selection2' },
                { label: 'Selection3' },
                { label: 'Selection4' },
                { label: 'Selection5' },
                { label: 'Selection6' },
            ],
        });

        wrapper.visibleTags = [
            { label: 'Selection1' },
            { label: 'Selection2' },
            { label: 'Selection3' },
            { label: 'Selection4' },
            { label: 'Selection5' },
        ];

        expect(wrapper.vm.numberOfHiddenTags).toBe(1);
    });

    it('should able to remove tag limit', async () => {
        const wrapper = await createWrapper({
            selections: [{ label: 'Selection1' }],
        });

        await wrapper.setData({
            tagLimit: true,
        });

        wrapper.vm.removeTagLimit();

        expect(wrapper.vm.tagLimit).toBe(false);
    });
});
