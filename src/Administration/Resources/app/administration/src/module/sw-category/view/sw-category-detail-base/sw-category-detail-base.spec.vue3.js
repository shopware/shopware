/**
 * @package content
 */
import { mount } from '@vue/test-utils_v3';

const categoryMock = {
    media: [],
    name: 'Computer parts',
    footerSalesChannels: [],
    navigationSalesChannels: [],
    serviceSalesChannels: [],
    productAssignmentType: 'product',
    isNew: () => false,
};

async function createWrapper() {
    if (Shopware.State.get('swCategoryDetail')) {
        Shopware.State.unregisterModule('swCategoryDetail');
    }

    Shopware.State.registerModule('swCategoryDetail', {
        namespaced: true,
        state: {
            category: categoryMock,
        },
    });

    return mount(await wrapTestComponent('sw-category-detail-base', { sync: true }), {
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
                'sw-single-select': {
                    template: '<input type="select" class="sw-single-select"></input>',
                    props: ['disabled'],
                },
                'sw-entity-tag-select': {
                    template: '<input type="select" class="sw-entity-tag-select"></input>',
                    props: ['disabled'],
                },
                'sw-category-detail-menu': {
                    template: '<div class="sw-category-detail-menu"></div>',
                },
            },
            mocks: {
                placeholder: () => {},
            },
        },
        props: {
            isLoading: false,
            manualAssignedProductsCount: 0,
        },
    });
}

describe('module/sw-category/view/sw-category-detail-base.spec', () => {
    it('should disable all interactive elements', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('input').forEach(element => {
            expect(element.props('disabled')).toBe(true);
        });
    });

    it('should enable all interactive elements', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('input').forEach(element => {
            expect(element.props('disabled')).toBe(false);
        });
    });
});
