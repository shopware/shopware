import { shallowMount } from '@vue/test-utils';

import 'src/module/sw-cms/component/sw-cms-list-item';


function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-cms-list-item'), {
        propsData: {
            page: {
                name: 'My custom layout',
                type: 'product_list',
                translated: {
                    name: 'some-name'
                },
                sections: [
                    {
                        name: 'Section 1',
                        blocks: [
                            {
                                name: 'Test block',
                                type: 'product-listing',
                                slots: []
                            }
                        ]
                    }
                ]
            }
        }
    });
}

describe('module/sw-cms/page/sw-cms-list-item', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should display whether the cms-page is set as default', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);

        await wrapper.setProps({ isDefault: true });
        expect(wrapper.find('.sw-cms-list-item__is-default').text()).toBe('sw-cms.components.cmsListItem.defaultLayoutProductList');

        await wrapper.setProps({ isDefault: false });
        expect(wrapper.find('.sw-cms-list-item__is-default').exists()).toBe(false);
    });
});
