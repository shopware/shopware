/**
 * @package buyers-experience
 */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElSidebarFilter from 'src/module/sw-cms/elements/sidebar-filter/component';

Shopware.Component.register('sw-cms-el-sidebar-filter', swCmsElSidebarFilter);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-el-sidebar-filter'), {
        propsData: {
            element: {},
        },
        stubs: {
            'sw-icon': true,
        },
        provide: {
            cmsService: {
                getCmsElementRegistry: () => ({
                    'sidebar-filter': {},
                }),
            },
        },
    });
}

describe('src/module/sw-cms/elements/sidebar-filter/component', () => {
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
