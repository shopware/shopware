/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import swCmsStageAddSection from 'src/module/sw-cms/component/sw-cms-stage-add-section';

Shopware.Component.register('sw-cms-stage-add-section', swCmsStageAddSection);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-stage-add-section'), {
        propsData: {},
        stubs: {
            'sw-icon': true,
        },
        provide: {
            cmsService: {},
        },
    });
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
