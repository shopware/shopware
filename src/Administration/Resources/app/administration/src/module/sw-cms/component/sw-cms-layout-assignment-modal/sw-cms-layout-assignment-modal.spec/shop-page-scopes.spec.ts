/**
 * @sw-package discovery
 */
import type { ComponentPublicInstance } from 'vue';
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const PAGE_ID = 'layout-id';

// In "all sales channels" (null) the layout is the contact and the newsletter page,
// in the storefront channel it is the contact page.
const storedConfig: Record<string, Record<string, string>> = {
    null: {
        'core.basicInformation.contactPage': PAGE_ID,
        'core.basicInformation.newsletterPage': PAGE_ID,
    },
    storefront_id: {
        'core.basicInformation.contactPage': PAGE_ID,
    },
};

async function createWrapper(batchSave: jest.Mock) {
    const emptyCollection = (entity: 'category' | 'product' | 'landing_page') =>
        new EntityCollection(`/${entity}`, entity, Shopware.Context.api, new Criteria(1, 25), []);
    const origin = { categories: emptyCollection('category') };

    return mount(await wrapTestComponent('sw-cms-layout-assignment-modal', { sync: true }), {
        attachTo: document.body,
        props: {
            page: {
                categories: emptyCollection('category'),
                products: emptyCollection('product'),
                landingPages: emptyCollection('landing_page'),
                type: 'page',
                id: PAGE_ID,
                getOrigin: () => origin,
            },
        },
        global: {
            stubs: {
                'mt-tabs': {
                    name: 'mt-tabs',
                    emits: ['new-item-active'],
                    props: ['items', 'defaultItem', 'positionIdentifier'],
                    template: '<div class="mt-tabs"></div>',
                },
                'mt-banner': {
                    template: '<div class="mt-banner"><slot /></div>',
                },
                'sw-category-tree-field': true,
                'sw-entity-single-select': {
                    name: 'sw-entity-single-select',
                    props: ['value'],
                    emits: ['update:value'],
                    template: '<div class="sw-entity-single-select-stub"></div>',
                },
                'sw-inherit-wrapper': {
                    props: ['value'],
                    emits: ['update:value'],
                    template: `
                        <div class="sw-inherit-wrapper-stub">
                            <slot
                                name="content"
                                :current-value="value"
                                :is-inherited="false"
                                :update-current-value="(newValue) => $emit('update:value', newValue)"
                            />
                        </div>
                    `,
                },
                'sw-multi-select': {
                    name: 'sw-multi-select',
                    props: ['value', 'options'],
                    emits: ['update:value'],
                    template: '<div class="sw-multi-select-stub"></div>',
                },
                'sw-loader': true,
                'sw-cms-product-assignment': true,
                'sw-help-text': true,
                'router-link': true,
                'sw-extension-component-section': true,
                transition: false,
            },
            provide: {
                systemConfigApiService: {
                    getValues: jest.fn((domain: string, salesChannelId: string) => {
                        return Promise.resolve({ ...(storedConfig[salesChannelId] ?? {}) });
                    }),
                    batchSave,
                },
                shortcutService: {
                    stopEventListener: () => {},
                    startEventListener: () => {},
                },
            },
        },
    });
}

type Wrapper = Awaited<ReturnType<typeof createWrapper>>;

async function openShopPagesTab(wrapper: Wrapper) {
    wrapper.getComponent<ComponentPublicInstance>({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'shop_pages');
    await flushPromises();
}

async function selectSalesChannelScope(wrapper: Wrapper, salesChannelId: string) {
    wrapper
        .getComponent<ComponentPublicInstance>({ name: 'sw-entity-single-select' })
        .vm.$emit('update:value', salesChannelId);
    await flushPromises();
}

async function chooseShopPages(wrapper: Wrapper, shopPages: string[]) {
    wrapper.getComponent<ComponentPublicInstance>({ name: 'sw-multi-select' }).vm.$emit('update:value', shopPages);
    await flushPromises();
}

async function clickSave(wrapper: Wrapper) {
    await wrapper.find('.sw-cms-layout-assignment-modal__action-confirm').trigger('click');
    await flushPromises();
}

describe('src/module/sw-cms/component/sw-cms-layout-assignment-modal/shop-page-scopes', () => {
    beforeEach(() => {
        // @ts-expect-error - activeAclRoles is an untyped test global
        global.activeAclRoles = ['system.system_config'];
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'saves a shop page removal made in a sales channel scope that is no longer selected',
        async () => {
            const batchSave = jest.fn(() => Promise.resolve());
            const wrapper = await createWrapper(batchSave);
            await flushPromises();
            await openShopPagesTab(wrapper);

            // In "all sales channels": remove the newsletter page, then switch to the storefront scope.
            await chooseShopPages(wrapper, ['core.basicInformation.contactPage']);
            await selectSalesChannelScope(wrapper, 'storefront_id');

            await clickSave(wrapper);

            expect(batchSave).not.toHaveBeenCalled();
            expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBe(true);

            await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
            await flushPromises();

            expect(batchSave).toHaveBeenCalledTimes(1);
            expect(batchSave).toHaveBeenCalledWith({
                null: {
                    'core.basicInformation.contactPage': PAGE_ID,
                    'core.basicInformation.newsletterPage': null,
                },
                storefront_id: {
                    'core.basicInformation.contactPage': PAGE_ID,
                },
            });
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])(
        'saves a shop page removal made after switching to another sales channel scope',
        async () => {
            const batchSave = jest.fn(() => Promise.resolve());
            const wrapper = await createWrapper(batchSave);
            await flushPromises();
            await openShopPagesTab(wrapper);

            // Switch to the storefront scope and remove its contact page.
            await selectSalesChannelScope(wrapper, 'storefront_id');
            await chooseShopPages(wrapper, []);

            await clickSave(wrapper);

            expect(wrapper.find('.sw-cms-layout-assignment-modal__confirm-text-shop-pages').exists()).toBe(true);

            await wrapper.find('.sw-cms-layout-assignment-modal__action-changes-confirm').trigger('click');
            await flushPromises();

            expect(batchSave).toHaveBeenCalledWith({
                null: {
                    'core.basicInformation.contactPage': PAGE_ID,
                    'core.basicInformation.newsletterPage': PAGE_ID,
                },
                storefront_id: {
                    'core.basicInformation.contactPage': null,
                },
            });
        },
    );
});
