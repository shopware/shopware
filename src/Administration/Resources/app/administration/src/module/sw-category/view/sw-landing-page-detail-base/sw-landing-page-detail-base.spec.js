/**
 * @sw-package buyers-experience
 */
import { mount } from '@vue/test-utils';

async function createWrapper({
    landingPage = {
        cmsPageId: null,
    },
} = {}) {
    Shopware.Store.get('swCategoryDetail').$reset();
    Shopware.Store.get('swCategoryDetail').category = {
        media: [],
        name: 'Computer parts',
        footerSalesChannels: [],
        navigationSalesChannels: [],
        serviceSalesChannels: [],
        productAssignmentType: 'product',
        isNew: () => false,
    };
    Shopware.Store.get('swCategoryDetail').landingPage = landingPage;

    return mount(await wrapTestComponent('sw-landing-page-detail-base', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': {
                    template:
                        '<input class="sw-text-field" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-switch-field': {
                    template:
                        '<input class="sw-field sw-switch-field" type="checkbox" :value="value" @change="$emit(\'update:value\', $event.target.checked)" />',
                    props: [
                        'value',
                        'disabled',
                    ],
                },
                'sw-entity-tag-select': {
                    template: '<input type="select" class="sw-entity-tag-select"/>',
                    props: ['disabled'],
                },
                'sw-entity-multi-select': true,
                'mt-banner': true,
                'sw-textarea-field': true,
                'sw-custom-field-set-renderer': true,
            },
            computed: {
                landingPage() {
                    return Shopware.Store.get('swCategoryDetail').landingPage;
                },
            },
        },
        props: {
            isLoading: false,
        },
    });
}

describe('module/sw-category/view/sw-landing-page-detail-base.spec', () => {
    it('should return true if a layout is set', async () => {
        const wrapper = await createWrapper({
            landingPage: {
                cmsPageId: '123456789',
            },
        });

        expect(wrapper.vm.isLayoutSet).toBe(true);
    });

    it('should return false if no layout is set', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.isLayoutSet).toBe(false);
    });
});
