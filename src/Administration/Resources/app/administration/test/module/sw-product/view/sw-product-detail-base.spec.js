import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/view/sw-product-detail-base';
import cmsStore from 'src/module/sw-cms/state/cms-page.state';
import Vuex from 'vuex';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-product-detail-base'), {
        localVue,
        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content">CONTENT</slot>
                        <slot></slot>
                    </div>`
            },
            'sw-product-detail-base__review-card': true,
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div>
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>`
            },
            'sw-product-packaging-form': true,
            'sw-product-seo-form': true,
            'sw-product-settings-form': true,
            'sw-product-category-form': true,
            'sw-product-deliverability-form': true,
            'sw-product-price-form': true,
            'sw-product-basic-form': true,
            'sw-product-feature-set-form': true,
            'sw-inherit-wrapper': true,
            'sw-empty-state': true,
            'sw-card': {
                template: '<div><slot></slot><slot name="grid"></slot></div>'
            },
            'sw-context-menu-item': true,
            'sw-product-layout-assignment': true
        },
        mocks: {
            $tc: () => {},
            $store: Shopware.State._store
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve('bar');
                    },

                    get: (id) => {
                        return Promise.resolve({ id });
                    }
                })
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-base', () => {
    let wrapper;
    const mockReviews = [{
        name: 'Billions',
        id: '1000000000'
    }];
    mockReviews.total = 1;

    beforeAll(() => {
        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            getters: {
                isLoading: () => false
            },
            state: {
                parentProduct: {
                    media: [],
                    reviews: [{
                        id: '1a2b3c',
                        entity: 'review',
                        customerId: 'd4c3b2a1',
                        productId: 'd4c3b2a1',
                        salesChannelId: 'd4c3b2a1'
                    }]
                },
                product: {
                    media: [],
                    reviews: [{
                        id: '1a2b3c',
                        entity: 'review',
                        customerId: 'd4c3b2a1',
                        productId: 'd4c3b2a1',
                        salesChannelId: 'd4c3b2a1'
                    }]
                },
                loading: {
                    product: false,
                    media: false
                }
            },
            mutations: {
                setProduct(state, newProduct) {
                    state.product = newProduct;
                }
            }
        });

        Shopware.State.registerModule('cmsPageState', cmsStore);
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be able to delete', async () => {
        wrapper = createWrapper();
        await wrapper.setData({
            reviewItemData: mockReviews,
            total: 1
        });
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-product-detail-base__review-delete');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete', async () => {
        wrapper = createWrapper([
            'product.editor'
        ]);
        await wrapper.setData({
            reviewItemData: mockReviews,
            total: 1
        });

        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-product-detail-base__review-delete');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit', async () => {
        wrapper = createWrapper();
        await wrapper.setData({
            reviewItemData: mockReviews,
            total: 1
        });
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-product-detail-base__review-edit');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit', async () => {
        wrapper = createWrapper([
            'product.editor'
        ]);
        await wrapper.setData({
            reviewItemData: mockReviews,
            total: 1
        });
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-product-detail-base__review-edit');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should be able to assign a new product page layout', async () => {
        wrapper.vm.onLayoutSelect('ad6b4ff55b9949f2a6b8bdab80d64c64');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.product.cmsPageId).toBe('ad6b4ff55b9949f2a6b8bdab80d64c64');
        expect(Shopware.State.get('cmsPageState').currentPage.id).toBe('ad6b4ff55b9949f2a6b8bdab80d64c64');
    });

    it('should be able to remove product page layout', async () => {
        wrapper.vm.onLayoutReset();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.product.cmsPageId).toBe(null);
        expect(Shopware.State.get('cmsPageState').currentPage.id).toBe(null);
    });
});
