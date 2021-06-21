import { shallowMount, createLocalVue, config } from '@vue/test-utils';
import VueRouter from 'vue-router';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';
import 'src/module/sw-product/view/sw-product-detail-properties';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import Vuex from 'vuex';

/**
 * @deprecated tag:v6.5.0 - Will be removed, use `sw-product-properties` instead
 * @feature-deprecated (flag:FEATURE_NEXT_12437)
 */
describe('src/module/sw-product/view/sw-product-detail-properties', () => {
    Shopware.State.registerModule('swProductDetail', productStore);

    const createWrapper = () => {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const localVue = createLocalVue();

        localVue.use(VueRouter);
        localVue.use(Vuex);
        localVue.directive('tooltip', {});
        localVue.filter('asset', key => key);

        return shallowMount(Shopware.Component.build('sw-product-detail-properties'), {
            localVue,
            stubs: {
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-card': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-empty-state': true,
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-inheritance-switch': true,
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true
            },
            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        if (repositoryName === 'property_group_option') {
                            return {
                                search: () => Promise.resolve({
                                    0: { id: 'optionId', name: 'optionName' },
                                    total: 1
                                })
                            };
                        }

                        return {};
                    }
                },
                acl: {
                    can: () => true
                }
            }
        });
    };

    it('should be able to instantiate', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: 'parentProductId',
            options: []
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId'
        });

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const emptyStateElement = wrapper.get('.sw-product-detail-properties__empty-state-card');
        const routerLink = emptyStateElement.find('.sw-product-detail-properties__parent-properties-link');

        expect(routerLink.exists()).toBe(true);
        expect(wrapper.vm.isChild).toBe(true);
        expect(wrapper.vm.isInherited).toBe(true);
    });

    it('should show empty state for main product', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            prices: []
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId'
        });

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBeFalsy();
        expect(wrapper.vm.isInherited).toBeFalsy();
    });
});
