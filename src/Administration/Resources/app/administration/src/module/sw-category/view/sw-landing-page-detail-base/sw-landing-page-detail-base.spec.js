/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { deepMergeObject } from 'src/core/service/utils/object.utils';

async function createWrapper(state = {}) {
    if (Shopware.State.get('swCategoryDetail')) {
        Shopware.State.unregisterModule('swCategoryDetail');
    }

    Shopware.State.registerModule('swCategoryDetail', {
        namespaced: true,
        state: deepMergeObject({
            category: {
                media: [],
                name: 'Computer parts',
                footerSalesChannels: [],
                navigationSalesChannels: [],
                serviceSalesChannels: [],
                productAssignmentType: 'product',
                isNew: () => false,
            },
            landingPage: {
                cmsPageId: null,
            },
        }, state),
    });

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
                    template: '<input class="sw-text-field" :value="value" @input="$emit(\'update:value\', $event.target.value)" />',
                    props: ['value', 'disabled'],
                },
                'sw-switch-field': {
                    template: '<input class="sw-field sw-switch-field" type="checkbox" :value="value" @change="$emit(\'update:value\', $event.target.checked)" />',
                    props: ['value', 'disabled'],
                },
                'sw-entity-tag-select': {
                    template: '<input type="select" class="sw-entity-tag-select"/>',
                    props: ['disabled'],
                },
            },
            mocks: {
                placeholder: () => {},
            },
            computed: {
                landingPage() {
                    return Shopware.State.get('swCategoryDetail').landingPage;
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
