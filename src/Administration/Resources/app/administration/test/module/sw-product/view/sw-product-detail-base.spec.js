import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-product/view/sw-product-detail-base';
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
            'sw-media-modal-v2': true
        },
        mocks: {
            $tc: snippetPath => snippetPath,
            $store: Shopware.State._store
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve('bar')
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
                isActive: () => false
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
                    getEntityName: () => 'product',
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
                setLoading: () => true
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    /**
     * Remove the test when feature flag "FEATURE_NEXT_12429" is active because
     * its relevant view was moved from this component to `sw-product-detail-reviews` component and
     * this case was covered there as well.
     */
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

    /**
     * Remove the test when feature flag "FEATURE_NEXT_12429" is active because
     * its relevant view was moved from this component to `sw-product-detail-reviews` component and
     * this case was covered there as well.
     */
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

    /**
     * Remove the test when feature flag "FEATURE_NEXT_12429" is active because
     * its relevant view was moved from this component to `sw-product-detail-reviews` component and
     * this case was covered there as well.
     */
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

    /**
     * Remove the test when feature flag "FEATURE_NEXT_12429" is active because
     * its relevant view was moved from this component to `sw-product-detail-reviews` component and
     * this case was covered there as well.
     */
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

    it('should get media default folder id when component got created', () => {
        wrapper.vm.getMediaDefaultFolderId = jest.fn(() => {
            return Promise.resolve(Shopware.Utils.createId());
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getMediaDefaultFolderId).toHaveBeenCalledTimes(1);
        wrapper.vm.getMediaDefaultFolderId.mockRestore();
    });

    it('should turn on media modal', async () => {
        await wrapper.setData({
            showMediaModal: true
        });

        const mediaModal = wrapper.find('sw-media-modal-v2-stub');

        expect(mediaModal.exists()).toBeTruthy();
        expect(mediaModal.attributes('entitycontext')).toBe('product');
    });

    it('should turn off media modal', async () => {
        await wrapper.setData({
            showMediaModal: false
        });

        const mediaModal = wrapper.find('sw-media-modal-v2-stub');

        expect(mediaModal.exists()).toBeFalsy();
    });

    it('should be able to add a new media', async () => {
        wrapper.vm.addMedia = jest.fn(() => Promise.resolve());

        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };
        await wrapper.vm.onAddMedia([media]);
        await wrapper.setData({
            product: {
                media: [
                    media
                ]
            }
        });

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(media);
        expect(wrapper.vm.product.media).toEqual(expect.arrayContaining([media]));

        wrapper.vm.addMedia.mockRestore();
    });

    it('should not be able to add a new media', async () => {
        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };

        wrapper.vm.addMedia = jest.fn(() => Promise.reject(media));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onAddMedia([media]);

        expect(wrapper.vm.addMedia).toHaveBeenCalledWith(media);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-product.mediaForm.errorMediaItemDuplicated'
        });

        wrapper.vm.addMedia.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should set media as cover', async () => {
        const media = { id: 'id', fileName: 'fileName', fileSize: 101 };

        await wrapper.vm.setMediaAsCover(media);

        expect(wrapper.vm.product.coverId).toBe(media.id);
    });
});
