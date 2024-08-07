/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-list-item', {
        sync: true,
    }), {
        props: {
            page: {
                name: 'My custom layout',
                type: 'product_list',
                translated: {
                    name: 'some-name',
                },
                sections: [
                    {
                        name: 'Section 1',
                        blocks: [
                            {
                                name: 'Test block',
                                type: 'product-listing',
                                slots: [],
                            },
                        ],
                    },
                ],
            },
        },
        global: {
            stubs: {
                'sw-icon': true,
            },
        },
    });
}

describe('module/sw-cms/page/sw-cms-list-item', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display whether the cms-page is set as default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);

        await wrapper.setProps({ isDefault: true });
        expect(wrapper.find('.sw-cms-list-item__is-default').text()).toBe('sw-cms.components.cmsListItem.defaultLayoutProductList');

        await wrapper.setProps({ isDefault: false });
        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);
    });
});
