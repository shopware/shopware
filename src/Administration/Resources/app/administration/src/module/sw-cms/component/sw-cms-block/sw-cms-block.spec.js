import { shallowMount } from '@vue/test-utils';
import swCmsBlock from 'src/module/sw-cms/component/sw-cms-block';

Shopware.Component.register('sw-cms-block', swCmsBlock);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-block'), {
        propsData: {
            block: {}
        },
        provide: {
            cmsService: {}
        }
    });
}
describe('module/sw-cms/component/sw-cms-block', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the overlay should exist and be visible', async () => {
        const wrapper = await createWrapper();

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeTruthy();
        expect(overlay.isVisible()).toBeTruthy();
    });

    it('the overlay should not exist', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeFalsy();
    });
});
