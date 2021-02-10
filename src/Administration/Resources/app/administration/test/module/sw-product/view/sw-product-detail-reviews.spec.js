import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-reviews';

const { Component, State } = Shopware;

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-detail-reviews'), {
        localVue,
        mocks: {
            $t: key => key,
            $tc: key => key,
            $store: State._store
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve([]);
                    },
                    delete: () => {
                        return Promise.resolve();
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
        },
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot name="grid"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-data-grid': {
                props: ['dataSource'],
                template: `
                    <div class="sw-data-grid">
                        <template v-for="item in dataSource">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-empty-state': true,
            'sw-context-menu-item': true,
            'sw-modal': true
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-reviews', () => {
    const dataSource = [
        { id: '101', productId: '01', status: true, points: 4 },
        { id: '102', productId: '02', status: true, points: 5 }
    ];

    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {}
            },
            getters: {
                isLoading: () => false
            }
        });
    });

    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit a review', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        await wrapper.setData({ dataSource, total: 2 });

        const editMenuItem = wrapper.find('.sw-product-detail-reviews__action-edit');
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to edit a review', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ dataSource, total: 2 });

        const editMenuItem = wrapper.find('.sw-product-detail-reviews__action-edit');
        expect(editMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should be able to delete a review', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        await wrapper.setData({ dataSource, total: 2 });

        const deleteMenuItem = wrapper.find('.sw-product-detail-reviews__action-delete');
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should not be able to delete a review', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({ dataSource, total: 2 });

        const deleteMenuItem = wrapper.find('.sw-product-detail-reviews__action-delete');
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should turn on the delete modal', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onStartReviewDelete({ id: '101' });

        const modal = wrapper.find('sw-modal-stub');

        expect(wrapper.vm.deleteReviewId).toBe('101');
        expect(wrapper.vm.showReviewDeleteModal).toBe(true);
        expect(modal.exists()).toBeTruthy();
        expect(modal.text()).toContain('sw-product.reviewForm.modal.confirmText');
    });

    it('should turn off the delete modal', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.onCancelReviewDelete();

        const modal = wrapper.find('sw-modal-stub');

        expect(wrapper.vm.deleteReviewId).toBe(null);
        expect(wrapper.vm.showReviewDeleteModal).toBe(false);
        expect(modal.exists()).toBeFalsy();
    });

    it('should get reviews when component got created', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getReviews = jest.fn();

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getReviews).toHaveBeenCalled();
        wrapper.vm.getReviews.mockRestore();
    });

    it('should get reviews when product id got changed', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getReviews = jest.fn();

        await wrapper.setData({ product: { id: '101' } });

        expect(wrapper.vm.getReviews).toHaveBeenCalled();
        wrapper.vm.getReviews.mockRestore();
    });

    it('should get reviews after deleting a review', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getReviews = jest.fn();

        await wrapper.vm.onConfirmReviewDelete();

        expect(wrapper.vm.showReviewDeleteModal).toBe(false);
        expect(wrapper.vm.getReviews).toHaveBeenCalled();
        wrapper.vm.getReviews.mockRestore();
    });

    it('should get reviews after changing page', async () => {
        const wrapper = createWrapper();
        wrapper.vm.getReviews = jest.fn();

        await wrapper.vm.onChangePage({ page: 2, limit: 10 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(10);
        expect(wrapper.vm.getReviews).toHaveBeenCalled();
        wrapper.vm.getReviews.mockRestore();
    });
});
