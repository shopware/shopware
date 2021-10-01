import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-media-form';
import 'src/app/component/base/sw-product-image';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/utils/sw-popover';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});

    return shallowMount(Shopware.Component.build('sw-product-media-form'), {
        localVue,
        mocks: {
            $store: new Vuex.Store({
                modules: {
                    swProductDetail: {
                        namespaced: true,
                        getters: {
                            isLoading: () => false
                        }
                    }
                }
            })
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }

        },
        stubs: {
            'sw-upload-listener': true,
            'sw-product-image': Shopware.Component.build('sw-product-image'),
            'sw-media-upload-v2': true,
            'sw-media-preview-v2': true,
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-icon': true,
            'sw-label': true,
            'sw-context-menu': Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
            'sw-context-button': Shopware.Component.build('sw-context-button')
        }
    });
}

describe('module/sw-product/component/sw-product-media-form', () => {
    beforeAll(() => {
        const product = {
            cover: {
                mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                position: 1,
                id: '520a8b95abc2446db77b173fcd718567',
                media: {
                    id: 'c621b5f556424911964e848fa1b7e8a5'
                }
            },
            coverId: '520a8b95abc2446db77b173fcd718567',
            media: [
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '520a8b95abc2446db77b173fcd718567',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5'
                    }
                },
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '5a73a7f88b544a9ab52b2e795c95c7a7',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5'
                    }
                }
            ]
        };
        product.getEntityName = () => 'T-Shirt';

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: product
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should only show 1 cover', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        let coverCount = 0;
        wrapper.vm.mediaItems.forEach(mediaItem => {
            if (wrapper.vm.isCover(mediaItem)) {
                coverCount += 1;
            }
        });

        expect(coverCount).toBe(1);
    });

    it('should emit an event when onOpenMedia() function is called', () => {
        const wrapper = createWrapper();

        wrapper.vm.onOpenMedia();

        const pageChangeEvents = wrapper.emitted()['media-open'];
        expect(pageChangeEvents.length).toBe(1);
    });

    it('should can show cover when `showCoverLabel` is true', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeTruthy();
    });

    it('should not show cover when `showCoverLabel` is false', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            showCoverLabel: false
        });

        await wrapper.vm.$nextTick();
        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-product-media-form__previews').find('.sw-product-image__context-button').trigger('click');
        await wrapper.vm.$nextTick();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons.length).toBe(1);
        expect(buttons.at(0).text()).toContain('Remove');
    });
});
