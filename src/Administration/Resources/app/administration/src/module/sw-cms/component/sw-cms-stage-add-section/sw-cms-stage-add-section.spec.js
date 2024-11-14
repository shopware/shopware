/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-stage-add-section', {
            sync: true,
        }),
        {
            props: {},
            global: {
                stubs: {
                    'sw-icon': true,
                    'sw-cms-stage-section-selection': true,
                },
                provide: {
                    cmsService: {},
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-stage-add-section', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('set a is--disabled class to wrapper', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });

    it('do not set a is--disabled class to wrapper', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });
});
