/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import swBulkEditProductMediaForm from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-media-form';
import swProductMediaForm from 'src/module/sw-product/component/sw-product-media-form';
import 'src/app/component/base/sw-product-image';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/utils/sw-popover';

Shopware.Component.register('sw-product-media-form', swProductMediaForm);
Shopware.Component.extend('sw-bulk-edit-product-media-form', 'sw-product-media-form', swBulkEditProductMediaForm);

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});

    return shallowMount(await Shopware.Component.build('sw-bulk-edit-product-media-form'), {
        localVue,
        mocks: {
            $store: new Vuex.Store({
                modules: {
                    swProductDetail: {
                        namespaced: true,
                        getters: {
                            isLoading: () => false,
                        },
                    },
                },
            }),
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },

        },
        stubs: {
            'sw-upload-listener': true,
            'sw-product-image': await Shopware.Component.build('sw-product-image'),
            'sw-media-upload-v2': true,
            'sw-media-preview-v2': true,
            'sw-product-media-form': true,
            'sw-icon': true,
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
        },
    });
}

describe('src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-media-form', () => {
    beforeAll(() => {
        const product = {
            cover: {
                mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                position: 1,
                id: '520a8b95abc2446db77b173fcd718567',
                media: {
                    id: 'c621b5f556424911964e848fa1b7e8a5',
                },
            },
            coverId: '520a8b95abc2446db77b173fcd718567',
            media: [
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '520a8b95abc2446db77b173fcd718567',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5',
                    },
                },
                {
                    mediaId: 'c621b5f556424911964e848fa1b7e8a5',
                    position: 1,
                    id: '5a73a7f88b544a9ab52b2e795c95c7a7',
                    media: {
                        id: 'c621b5f556424911964e848fa1b7e8a5',
                    },
                },
            ],
        };
        product.getEntityName = () => 'T-Shirt';

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: product,
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });

    it('should dont have button Use as cover', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.is--cover').exists()).toBeFalsy();

        await wrapper.find('.sw-product-media-form__previews').find('.sw-product-image__context-button').trigger('click');
        await wrapper.vm.$nextTick();

        const buttons = wrapper.find('.sw-context-menu').findAll('.sw-context-menu-item__text');
        expect(buttons).toHaveLength(1);
        expect(buttons.at(0).text()).toContain('Remove');
    });
});
