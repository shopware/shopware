/**
 * @package content
 */
import { mount } from '@vue/test-utils';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper() {
    const salesChannelCollection = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
        {
            id: '',
            name: '',
            homeEnabled: false,
            homeName: '',
            homeMetaTitle: '',
            homeMetaDescription: '',
            homeKeywords: '',
            homeCmsPageId: '',
            homeCmsPage: null,
            translated: {
                name: '',
            },
        },
    ]);

    return mount(await wrapTestComponent('sw-category-entry-point-modal', { sync: true }), {
        global: {
            stubs: {
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                          <slot name="modal-header"></slot>
                          <slot></slot>
                          <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-single-select': true,
                'sw-text-field': true,
                'sw-textarea-field': true,
                'sw-cms-list-item': true,
                'sw-switch-field': true,
                'sw-button': true,
            },
            provide: {
                cmsPageTypeService: {
                    getTypes: () => {
                        return [{
                            name: 'page',
                            title: 'page',
                        }, {
                            name: 'landingpage',
                            title: 'landingpage',
                        }, {
                            name: 'product_list',
                            title: 'product_list',
                        }, {
                            name: 'product_detail',
                            title: 'product_detail',
                        }];
                    },
                },
            },
        },
        props: {
            salesChannelCollection,
        },
    });
}

describe('src/module/sw-category/component/sw-category-entry-point-modal', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have enabled fields', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-category-entry-point-modal__show-in-main-navigation').attributes().disabled)
            .toBeUndefined();
        expect(wrapper.find('.sw-category-entry-point-modal__layout-item').attributes().disabled)
            .toBeUndefined();
        expect(wrapper.find('.sw-category-entry-point-modal__meta-title').attributes().disabled)
            .toBeUndefined();
        expect(wrapper.find('.sw-category-entry-point-modal__meta-description').attributes().disabled)
            .toBeUndefined();
        expect(wrapper.find('.sw-category-entry-point-modal__seo-keywords').attributes().disabled)
            .toBeUndefined();
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-category-entry-point-modal__show-in-main-navigation').attributes().disabled)
            .toBe('true');
        expect(wrapper.find('.sw-category-entry-point-modal__name-in-main-navigation').attributes().disabled)
            .toBe('true');
        expect(wrapper.find('.sw-category-entry-point-modal__layout-item').attributes().disabled)
            .toBe('true');
        expect(wrapper.find('.sw-category-entry-point-modal__meta-title').attributes().disabled)
            .toBe('true');
        expect(wrapper.find('.sw-category-entry-point-modal__meta-description').attributes().disabled)
            .toBe('true');
        expect(wrapper.find('.sw-category-entry-point-modal__seo-keywords').attributes().disabled)
            .toBe('true');
    });


    it('should have sales channel options which contain no changes', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.vm.salesChannelOptions).toHaveLength(1);
        expect(wrapper.vm.hasNotAppliedChanges()).toBe(false);
    });

    it('should be able to apply its local changes', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        // change the 'homeName' of the currently selected sales channel (the first one)
        wrapper.vm.selectedSalesChannel.homeName = 'newName';
        // original should still be untouched
        expect(wrapper.vm.salesChannelCollection[0].homeName).toBe('');

        // expect to be able to apply this change back to the original
        expect(wrapper.vm.hasNotAppliedChanges()).toBe(true);
        wrapper.vm.applyChanges();
        expect(wrapper.vm.salesChannelCollection[0].homeName).toBe('newName');
    });
});
