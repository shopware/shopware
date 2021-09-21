import { config, createLocalVue, mount } from '@vue/test-utils';
import flushPromises from 'flush-promises';
import VueRouter from 'vue-router';
import Vuex from 'vuex';
import 'src/app/component/structure/sw-page';
import 'src/app/component/structure/sw-card-view';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-empty-state';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/base/sw-card';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-text-editor';
import 'src/app/component/form/sw-textarea-field';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/sw-price-field';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/form/select/entity/sw-entity-single-select';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-multi-tag-select';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/module/sw-bulk-edit/page/sw-bulk-edit-product';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';
import 'src/module/sw-product/component/sw-product-visibility-select';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error';
import 'src/app/component/base/sw-modal';

const routes = [
    {
        name: 'sw.bulk.edit.product',
        path: 'index'
    },
    {
        name: 'sw.bulk.edit.product.save',
        path: '',
        component: Shopware.Component.build('sw-bulk-edit-save-modal'),
        meta: { $module: {
            title: 'sw-bulk-edit-product.general.mainMenuTitle'
        } },
        redirect: {
            name: 'sw.bulk.edit.product.save.confirm'
        },
        children: [
            {
                name: 'sw.bulk.edit.product.save.confirm',
                path: 'confirm',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-confirm'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.process',
                path: 'process',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-process'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.success',
                path: 'success',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-success'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            },
            {
                name: 'sw.bulk.edit.product.save.error',
                path: 'error',
                component: Shopware.Component.build('sw-bulk-edit-save-modal-error'),
                meta: { $module: {
                    title: 'sw-bulk-edit-product.general.mainMenuTitle'
                } }
            }
        ]
    }
];

const router = new VueRouter({
    routes
});

let bulkEditResponse = {
    data: {}
};


function createWrapper(productEntityOverride) {
    const productEntity = productEntityOverride ||
        {
            metaTitle: 'test'
        };

    const taxes = [{
        id: 'rate1',
        name: 'Rate 1',
        position: 1,
        taxRate: 19
    }, {
        id: 'rate2',
        name: 'Rate 2',
        position: 2,
        taxRate: 27
    }];

    // delete global $router and $routes mocks
    delete config.mocks.$router;
    delete config.mocks.$route;

    const localVue = createLocalVue();
    localVue.use(VueRouter);

    return mount(Shopware.Component.build('sw-bulk-edit-product'), {
        localVue,
        router,
        stubs: {
            'sw-page': Shopware.Component.build('sw-page'),
            'sw-loader': Shopware.Component.build('sw-loader'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-bulk-edit-custom-fields': Shopware.Component.build('sw-bulk-edit-custom-fields'),
            'sw-bulk-edit-change-type-field-renderer': Shopware.Component.build('sw-bulk-edit-change-type-field-renderer'),
            'sw-bulk-edit-form-field-renderer': Shopware.Component.build('sw-bulk-edit-form-field-renderer'),
            'sw-bulk-edit-change-type': Shopware.Component.build('sw-bulk-edit-change-type'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-empty-state': Shopware.Component.build('sw-empty-state'),
            'sw-button-process': Shopware.Component.build('sw-button-process'),
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-textarea-field': Shopware.Component.build('sw-textarea-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-container': Shopware.Component.build('sw-container'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-entity-single-select': Shopware.Component.build('sw-entity-single-select'),
            'sw-entity-multi-select': Shopware.Component.build('sw-entity-multi-select'),
            'sw-card-view': Shopware.Component.build('sw-card-view'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-price-field': Shopware.Component.build('sw-price-field'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-bulk-edit-save-modal': Shopware.Component.build('sw-bulk-edit-save-modal'),
            'sw-bulk-edit-product-visibility': true,
            'sw-product-visibility-select': true,
            'sw-custom-field-set-renderer': true,
            'sw-text-editor-toolbar': true,
            'sw-app-actions': true,
            'sw-search-bar': true,
            'sw-datepicker': true,
            'sw-text-editor': true,
            'sw-language-switch': true,
            'sw-notification-center': true,
            'sw-icon': true,
            'sw-multi-tag-select': true,
            'sw-entity-tag-select': true,
            'sw-product-properties': true,
            'sw-product-detail-context-prices': true,
            'sw-category-tree-field': true,
            'sw-bulk-edit-product-media': true,
            'sw-tabs': true,
            'sw-alert': true,
            'sw-label': true
        },
        props: {
            title: 'Foo bar'
        },
        mocks: {
            $store: new Vuex.Store({
                modules: {
                    swProductDetail: {
                        namespaced: true,
                        state: {
                            product: productEntity,
                            taxes: taxes
                        }
                    }
                }
            })
        },
        provide: {
            validationService: {},
            bulkEditApiFactory: {
                getHandler: () => {
                    return {
                        bulkEdit: (selectedIds) => {
                            if (selectedIds.length === 0) {
                                return Promise.reject();
                            }

                            return Promise.resolve(bulkEditResponse);
                        }
                    };
                }
            },
            repositoryFactory: {
                create: (entity) => {
                    if (entity === 'currency') {
                        return { search: () => Promise.resolve([{ id: 'currencyId1', isSystemDefault: true }]) };
                    }

                    if (entity === 'tax') {
                        return { search: () => Promise.resolve(taxes) };
                    }

                    if (entity === 'product') {
                        return {
                            create: () => Promise.resolve({
                                isNew: () => true
                            })
                        };
                    }

                    return { search: () => Promise.resolve([{ id: 'Id' }]) };
                }
            },
            repository: {
                get: () => Promise.resolve({})
            },
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            }
        }
    });
}

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-product', () => {
    let wrapper;

    beforeEach(() => {
        const mockResponses = global.repositoryFactoryMock.responses;
        mockResponses.addResponse({
            method: 'post',
            url: '/search/custom-field-set',
            status: 200,
            response: {
                data: [
                    {
                        id: Shopware.Utils.createId(),
                        attributes: {
                            id: Shopware.Utils.createId()
                        }
                    }
                ]
            }
        });

        Shopware.State.commit('shopwareApps/setSelectedIds', [Shopware.Utils.createId()]);
    });

    afterEach(() => {
        wrapper.destroy();
        wrapper.vm.$router.push({ path: 'confirm' });
    });

    it('should be a Vue.js component', async () => {
        wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be handled change data', async () => {
        wrapper = createWrapper();
        await flushPromises();

        const infoForm = wrapper.find('.sw-bulk-edit-product-base__info');
        const activeField = infoForm.find('.sw-bulk-edit-change-field-active');
        await activeField.find('.sw-bulk-edit-change-field__change input').trigger('click');

        await wrapper.vm.$nextTick();

        await activeField.find('.sw-field--switch__input input').trigger('click');

        expect(wrapper.vm.bulkEditProduct.active.isChanged).toBeTruthy();

        wrapper.vm.onProcessData();
        wrapper.vm.bulkEditSelected.forEach((change, index) => {
            const changeField = wrapper.vm.bulkEditSelected[index];
            expect(changeField.type).toEqual(change.type);
            expect(changeField.value).toEqual(change.value);
        });

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();
        expect(wrapper.vm.$route.path).toEqual('/confirm');
    });

    it('should close confirm modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        footerLeft.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('index');
    });

    it('should open process modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('/process');
    });

    it('should open success modal', async () => {
        wrapper = createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/success');
    });

    it('should open fail modal', async () => {
        bulkEditResponse = {
            data: null
        };

        await wrapper.destroy();
        wrapper = await createWrapper();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/error');
    });

    it('should be show empty state', async () => {
        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        wrapper = createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.product.messageEmptyTitle');
    });

    it('should be selected taxRate on click change tax field', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_17261'];

        const productEntity = {
            taxId: null
        };

        wrapper = createWrapper(productEntity);

        await flushPromises();

        const taxField = wrapper.find('.sw-bulk-edit-change-field-taxId');
        await taxField.find('.sw-bulk-edit-change-field__change input').trigger('click');

        await wrapper.vm.$nextTick();

        await taxField.find('.sw-select__selection').trigger('click');

        await wrapper.vm.$nextTick();

        const taxList = wrapper.find('.sw-select-result-list__item-list');
        const secondTax = taxList.find('.sw-select-option--1');
        await secondTax.trigger('click');

        expect(secondTax.text()).toBe('Rate 2');
        expect(wrapper.vm.taxRate.name).toBe('Rate 2');
    });

    it('should be correct data when the user overwrite minPurchase', async () => {
        const productEntity = {
            minPurchase: 2
        };
        wrapper = createWrapper(productEntity);

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('minPurchase');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value).toBe(2);
    });

    it('should be null for the value and the type is overwrite data when minPurchase set to clear type', async () => {
        const productEntity = {
            minPurchase: 2
        };
        wrapper = createWrapper(productEntity);

        await flushPromises();

        const minPurchaseField = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseField.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        await minPurchaseField.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();

        const changeTypeList = wrapper.find('.sw-select-result-list__item-list');
        const clearOption = changeTypeList.find('.sw-select-option--1');

        await clearOption.trigger('click');
        await wrapper.vm.$nextTick();
        expect(minPurchaseField.find('.sw-single-select__selection-text').text())
            .toBe('sw-bulk-edit.changeTypes.clear');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('minPurchase');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value).toBeNull();
    });

    it('should be getting the price', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_17261'];

        wrapper = createWrapper();

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').trigger('click');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).not.toHaveProperty('listPrice');
    });

    it('should be getting the list price when the price field is exists', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_17261'];

        wrapper = createWrapper();

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');
        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        const listPriceFields = listPriceFieldsForm.find('.sw-price-field');
        const listPriceGrossInput = listPriceFields.find('#listPrice-gross');
        await listPriceGrossInput.setValue('5');

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).toHaveProperty('listPrice');
    });

    it('should be getting the listPrice when the price field is enabled', async () => {
        global.activeFeatureFlags = ['FEATURE_NEXT_17261'];

        wrapper = createWrapper();

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').trigger('click');

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');

        const listPriceFields = listPriceFieldsForm.find('.sw-price-field');
        const listPriceGrossInput = listPriceFields.find('#listPrice-gross');
        await listPriceGrossInput.setValue('5');
        await wrapper.vm.$nextTick();

        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('price');
        expect(changeField.value[0]).toHaveProperty('currencyId');
        expect(changeField.value[0]).toHaveProperty('net');
        expect(changeField.value[0]).toHaveProperty('linked');
        expect(changeField.value[0]).toHaveProperty('gross');
        expect(changeField.value[0]).toHaveProperty('listPrice');
    });

    it('should be correct data when select categories', async () => {
        const productEntity = {
            categories: [{
                id: 'category1'
            }, {
                id: 'category2'
            }]
        };
        wrapper = createWrapper(productEntity);

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-categories');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('categories');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].id).toBe(productEntity.categories[0].id);
        expect(changeField.value[1].id).toBe(productEntity.categories[1].id);
    });

    it('should be correct data when select visibilities', async () => {
        const productEntity = {
            visibilities: [{
                productId: 'productId123',
                salesChannelId: 'salesChannelId345',
                visibility: 30
            }]
        };
        wrapper = createWrapper(productEntity);

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-visibilities');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('visibilities');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].productId).toBe('productId123');
        expect(changeField.value[0].salesChannelId).toBe('salesChannelId345');
        expect(changeField.value[0].visibility).toBe(30);
    });

    it('should be correct selections data', async () => {
        const productEntity = {
            media: [{
                id: 'a435755c6c4f4fb4b81ec32b4c07e06e',
                mediaId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca'
            }]
        };
        wrapper = createWrapper(productEntity);

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-media');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeMediaField = wrapper.vm.bulkEditSelected[0];
        expect(changeMediaField.field).toBe('media');
        expect(changeMediaField.type).toBe('overwrite');
        expect(changeMediaField.value[0].id).toBe('a435755c6c4f4fb4b81ec32b4c07e06e');
        expect(changeMediaField.value[0].mediaId).toBe('b7d2554b0ce847cd82f3ac9bd1c0dfca');
    });

    it('should be convert key to customSearchKeywords when the user changed searchKeywords', async () => {
        wrapper = createWrapper();

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-searchKeywords');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').trigger('click');
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('customSearchKeywords');
    });
});
