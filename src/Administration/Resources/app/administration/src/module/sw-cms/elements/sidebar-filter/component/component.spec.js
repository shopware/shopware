/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-sidebar-filter', {
            sync: true,
        }),
        {
            props: {
                element: {},
            },
            global: {
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
            },
        },
    );
}

describe('src/module/sw-cms/elements/sidebar-filter/component', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPage',
        });
    });

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
