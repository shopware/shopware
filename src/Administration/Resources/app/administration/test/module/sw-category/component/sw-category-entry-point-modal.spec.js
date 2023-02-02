import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-entry-point-modal';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

function createWrapper(privileges = [], additionalSalesChannels = []) {
    const localVue = createLocalVue();
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
                name: ''
            }
        },
        ...additionalSalesChannels
    ]);

    return shallowMount(Shopware.Component.build('sw-category-entry-point-modal'), {
        localVue,
        stubs: {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `
            },
            'sw-single-select': true,
            'sw-text-field': true,
            'sw-textarea-field': true,
            'sw-cms-list-item': true,
            'sw-switch-field': true,
            'sw-button': true
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            salesChannelCollection
        }
    });
}

describe('src/module/sw-category/component/sw-category-entry-point-modal', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        await wrapper.vm.$nextTick();

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
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

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
        const wrapper = createWrapper([
            'category.editor'
        ]);

        expect(wrapper.vm.salesChannelOptions.length).toBe(1);
        expect(wrapper.vm.hasNotAppliedChanges()).toBe(false);
    });

    it('should be able to apply its local changes', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

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
