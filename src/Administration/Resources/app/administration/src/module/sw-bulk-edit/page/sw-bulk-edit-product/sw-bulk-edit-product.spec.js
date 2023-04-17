/**
 * @package system-settings
 */
import { config, createLocalVue, mount } from '@vue/test-utils';
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
import swBulkEditProduct from 'src/module/sw-bulk-edit/page/sw-bulk-edit-product';
import swBulkEditCustomFields from 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import swBulkEditChangeTypeFieldRenderer from 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type-field-renderer';
import swBulkEditFormFieldRenderer from 'src/module/sw-bulk-edit/component/sw-bulk-edit-form-field-renderer';
import swBulkEditChangeType from 'src/module/sw-bulk-edit/component/sw-bulk-edit-change-type';
import swBulkEditProductVisibility from 'src/module/sw-bulk-edit/component/product/sw-bulk-edit-product-visibility';
import swProductVisibilitySelect from 'src/module/sw-product/component/sw-product-visibility-select';
import swBulkEditSaveModal from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal';
import swBulkEditSaveModalConfirm from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-confirm';
import swBulkEditSaveModalProcess from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-process';
import swBulkEditSaveModalSuccess from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-success';
import swBulkEditSaveModalError from 'src/module/sw-bulk-edit/component/sw-bulk-edit-save-modal-error';
import 'src/app/component/base/sw-modal';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';

Shopware.Component.register('sw-bulk-edit-product', swBulkEditProduct);
Shopware.Component.extend('sw-bulk-edit-custom-fields', 'sw-custom-field-set-renderer', swBulkEditCustomFields);
Shopware.Component.register('sw-bulk-edit-change-type-field-renderer', swBulkEditChangeTypeFieldRenderer);
Shopware.Component.extend('sw-bulk-edit-form-field-renderer', 'sw-form-field-renderer', swBulkEditFormFieldRenderer);
Shopware.Component.register('sw-bulk-edit-change-type', swBulkEditChangeType);
Shopware.Component.register('sw-bulk-edit-product-visibility', swBulkEditProductVisibility);
Shopware.Component.register('sw-product-visibility-select', swProductVisibilitySelect);
Shopware.Component.register('sw-bulk-edit-save-modal', swBulkEditSaveModal);
Shopware.Component.register('sw-bulk-edit-save-modal-confirm', swBulkEditSaveModalConfirm);
Shopware.Component.register('sw-bulk-edit-save-modal-process', swBulkEditSaveModalProcess);
Shopware.Component.register('sw-bulk-edit-save-modal-success', swBulkEditSaveModalSuccess);
Shopware.Component.register('sw-bulk-edit-save-modal-error', swBulkEditSaveModalError);

let bulkEditResponse = {
    data: {}
};

describe('src/module/sw-bulk-edit/page/sw-bulk-edit-product', () => {
    let wrapper;
    let routes;
    let router;
    const consoleError = console.error;

    async function createWrapper(productEntityOverride) {
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

        const rules = [{
            id: '1',
            name: 'Cart >= 0'
        }, {
            id: '2',
            name: 'Customer from USA'
        }];

        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const localVue = createLocalVue();
        localVue.use(VueRouter);

        return mount(await Shopware.Component.build('sw-bulk-edit-product'), {
            localVue,
            router,
            stubs: {
                'sw-page': await Shopware.Component.build('sw-page'),
                'sw-loader': await Shopware.Component.build('sw-loader'),
                'sw-button': await Shopware.Component.build('sw-button'),
                'sw-bulk-edit-custom-fields': await Shopware.Component.build('sw-bulk-edit-custom-fields'),
                'sw-bulk-edit-change-type-field-renderer': await Shopware.Component.build('sw-bulk-edit-change-type-field-renderer'),
                'sw-bulk-edit-form-field-renderer': await Shopware.Component.build('sw-bulk-edit-form-field-renderer'),
                'sw-bulk-edit-change-type': await Shopware.Component.build('sw-bulk-edit-change-type'),
                'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
                'sw-empty-state': await Shopware.Component.build('sw-empty-state'),
                'sw-button-process': await Shopware.Component.build('sw-button-process'),
                'sw-card': await Shopware.Component.build('sw-card'),
                'sw-ignore-class': true,
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-modal': await Shopware.Component.build('sw-modal'),
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-single-select': await Shopware.Component.build('sw-single-select'),
                'sw-number-field': await Shopware.Component.build('sw-number-field'),
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-textarea-field': await Shopware.Component.build('sw-textarea-field'),
                'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-container': await Shopware.Component.build('sw-container'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
                'sw-entity-single-select': await Shopware.Component.build('sw-entity-single-select'),
                'sw-entity-multi-select': await Shopware.Component.build('sw-entity-multi-select'),
                'sw-card-view': await Shopware.Component.build('sw-card-view'),
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-select-result': await Shopware.Component.build('sw-select-result'),
                'sw-popover': await Shopware.Component.build('sw-popover'),
                'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
                'sw-price-field': await Shopware.Component.build('sw-price-field'),
                'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
                'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
                'sw-bulk-edit-save-modal': await Shopware.Component.build('sw-bulk-edit-save-modal'),
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
                'sw-help-center': true,
                'sw-icon': true,
                'sw-multi-tag-select': true,
                'sw-entity-tag-select': true,
                'sw-product-properties': true,
                'sw-product-detail-context-prices': true,
                'sw-category-tree-field': true,
                'sw-bulk-edit-product-media': true,
                'sw-tabs': await Shopware.Component.build('sw-tabs'),
                'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
                'sw-alert': true,
                'sw-label': true,
                'sw-extension-component-section': true,
                'sw-inheritance-switch': true,
                'sw-bulk-edit-product-description': true,
            },
            props: {
                title: 'Foo bar'
            },
            mocks: {
                $store: new Vuex.Store({
                    modules: {
                        swProductDetail: {
                            namespaced: true,
                            state: () => ({
                                parentProduct: null,
                                product: productEntity,
                                taxes: taxes
                            }),
                            mutations: {
                                setParentProduct(state, parentProduct) {
                                    state.parentProduct = parentProduct;
                                },
                            },
                        }
                    }
                }),
                $route: { params: { parentId: 'null' } },
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
                            return {
                                search: () => Promise.resolve([{ id: 'currencyId1', isSystemDefault: true }]),
                                get: () => Promise.resolve({ id: '' })
                            };
                        }

                        if (entity === 'custom_field_set') {
                            return {
                                search: () => Promise.resolve([{ id: 'field-set-id-1' }]),
                                get: () => Promise.resolve({ id: '' })
                            };
                        }

                        if (entity === 'tax') {
                            return { search: () => Promise.resolve(taxes) };
                        }

                        if (entity === 'product') {
                            return {
                                create: () => Promise.resolve({
                                    isNew: () => true
                                }),
                                get: () => Promise.resolve(),
                            };
                        }

                        if (entity === 'rule') {
                            return { search: () => Promise.resolve(rules) };
                        }

                        return { search: () => Promise.resolve([{ id: 'Id' }]) };
                    }
                },
                orderDocumentApiService: {
                    create: () => {
                        return Promise.resolve();
                    },
                    download: () => {
                        return Promise.resolve();
                    },
                },
                repository: {
                    get: () => Promise.resolve({})
                },
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {}
                }
            },
            attachTo: document.body,
        });
    }

    beforeAll(async () => {
        routes = [
            {
                name: 'sw.product.detail.variants',
                path: 'variants',
            },
            {
                name: 'sw.bulk.edit.product',
                path: 'index'
            },
            {
                name: 'sw.bulk.edit.product.save',
                path: '',
                component: await Shopware.Component.build('sw-bulk-edit-save-modal'),
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
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-confirm'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-product.general.mainMenuTitle'
                        } }
                    },
                    {
                        name: 'sw.bulk.edit.product.save.process',
                        path: 'process',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-process'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-product.general.mainMenuTitle'
                        } }
                    },
                    {
                        name: 'sw.bulk.edit.product.save.success',
                        path: 'success',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-success'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-product.general.mainMenuTitle'
                        } }
                    },
                    {
                        name: 'sw.bulk.edit.product.save.error',
                        path: 'error',
                        component: await Shopware.Component.build('sw-bulk-edit-save-modal-error'),
                        meta: { $module: {
                            title: 'sw-bulk-edit-product.general.mainMenuTitle'
                        } }
                    }
                ]
            }
        ];

        router = new VueRouter({
            routes
        });
        const orgPush = router.push;
        router.push = (location) => {
            return orgPush.call(router, location).catch(() => {});
        };

        Shopware.Application.getContainer('factory').apiService.register('calculate-price', {
            calculatePrice: () => {
                return new Promise((resolve) => {
                    resolve({
                        data: {
                            calculatedTaxes: []
                        },
                    });
                });
            }
        });
    });

    beforeEach(async () => {
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
        console.error = jest.fn();
        global.allowedErrors = [
            ...global.allowedErrors,
            {
                method: 'warn',
                msg: /\[vuex\].*/
            }
        ];
    });

    afterEach(() => {
        wrapper.destroy();
        router.push({ path: 'confirm' });
        console.error = consoleError;
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be handled change data', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const infoForm = wrapper.find('.sw-bulk-edit-product-base__info');
        const activeField = infoForm.find('.sw-bulk-edit-change-field-active');
        await activeField.find('.sw-bulk-edit-change-field__change input').setChecked();

        await wrapper.vm.$nextTick();

        await activeField.find('.sw-field--switch__input input').setChecked();

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
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerLeft = wrapper.find('.footer-left');
        await footerLeft.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('index');
    });

    it('should open process modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        expect(wrapper.vm.$route.path).toEqual('/process');
        await flushPromises();
    });

    it('should open success modal', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/success');
    });

    // TODO: @inventory - Fix this test
    // eslint-disable-next-line jest/no-disabled-tests
    it.skip('should open fail modal', async () => {
        bulkEditResponse = {
            data: null
        };

        await wrapper.destroy();
        wrapper = await createWrapper();

        await wrapper.find('.sw-bulk-edit-product__save-action').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-bulk-edit-save-modal-confirm').exists()).toBeTruthy();

        const footerRight = wrapper.find('.footer-right');
        await footerRight.find('button').trigger('click');

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/process');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.$route.path).toEqual('/error');
    });

    it('should be show empty state', async () => {
        Shopware.State.commit('shopwareApps/setSelectedIds', []);
        wrapper = await createWrapper();
        await flushPromises();

        const emptyState = wrapper.find('.sw-empty-state');
        expect(emptyState.find('.sw-empty-state__title').text()).toBe('sw-bulk-edit.product.messageEmptyTitle');
    });

    it('should be selected taxRate on click change tax field', async () => {
        const productEntity = {
            taxId: null
        };

        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

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
        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const minPurchaseField = wrapper.find('.sw-bulk-edit-change-field-minPurchase');
        await minPurchaseField.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper();
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setChecked();

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
        wrapper = await createWrapper();
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setChecked();
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');
        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper();
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const priceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-price');
        const priceFields = priceFieldsForm.find('.sw-price-field');
        const priceGrossInput = priceFields.find('#price-gross');
        await priceGrossInput.setValue('6');
        await wrapper.vm.$nextTick();

        await priceFieldsForm.find('.sw-bulk-edit-change-field__change input').setChecked();

        const listPriceFieldsForm = wrapper.find('.sw-bulk-edit-change-field-listPrice');

        const listPriceFields = listPriceFieldsForm.find('.sw-price-field');
        const listPriceGrossInput = listPriceFields.find('#listPrice-gross');
        await listPriceGrossInput.setValue('5');
        await wrapper.vm.$nextTick();

        await listPriceFieldsForm.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-categories');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-visibilities');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();
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
        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const categoriesFieldForm = wrapper.find('.sw-bulk-edit-change-field-media');
        await categoriesFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeMediaField = wrapper.vm.bulkEditSelected[0];
        expect(changeMediaField.field).toBe('media');
        expect(changeMediaField.type).toBe('overwrite');
        expect(changeMediaField.value[0].id).toBe('a435755c6c4f4fb4b81ec32b4c07e06e');
        expect(changeMediaField.value[0].mediaId).toBe('b7d2554b0ce847cd82f3ac9bd1c0dfca');
    });

    it('should be convert key to customSearchKeywords when the user changed searchKeywords', async () => {
        wrapper = await createWrapper();

        await flushPromises();

        const minPurchaseFieldForm = wrapper.find('.sw-bulk-edit-change-field-searchKeywords');
        await minPurchaseFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();
        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];
        expect(changeField.field).toBe('customSearchKeywords');
    });

    it('should be correct data when select prices', async () => {
        const productEntity = {
            prices: [
                {
                    productId: 'productId',
                    ruleId: 'ruleId',
                    ruleName: 'Cart >= 0'
                }
            ]
        };

        wrapper = await createWrapper(productEntity);
        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null' } });

        await flushPromises();

        const advancedPricesFieldForm = wrapper.find('.sw-bulk-edit-product-base__advanced-prices');
        await advancedPricesFieldForm.find('.sw-bulk-edit-change-field__change input').setChecked();

        await wrapper.vm.$nextTick();

        wrapper.vm.onProcessData();

        const changeField = wrapper.vm.bulkEditSelected[0];

        expect(changeField.field).toBe('prices');
        expect(changeField.type).toBe('overwrite');
        expect(changeField.value[0].productId).toBe('productId');
        expect(changeField.value[0].ruleId).toBe('ruleId');
    });

    it('should restrict fields on including digitial products', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm.deliverabilityFormFields.length).toBeGreaterThan(1);

        wrapper.vm.$router.push({ name: 'sw.bulk.edit.product', params: { parentId: 'null', includesDigital: '1' } });

        await flushPromises();

        expect(wrapper.vm.deliverabilityFormFields.length).toEqual(1);
        expect(wrapper.vm.deliverabilityFormFields[0].name).toEqual('deliveryTimeId');
    });

    it('should set route meta module when component created', async () => {
        wrapper = await createWrapper();
        wrapper.vm.setRouteMetaModule = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.setRouteMetaModule).toHaveBeenCalled();
        expect(wrapper.vm.$route.meta.$module.color).toBe('#57D9A3');
        expect(wrapper.vm.$route.meta.$module.icon).toBe('regular-products');

        wrapper.vm.setRouteMetaModule.mockRestore();
    });

    it('should disable processing button', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            isLoading: false,
            bulkEditProduct: {
                taxId: {
                    isChanged: false,
                },
                price: {
                    isChanged: false,
                },
                purchasePrices: {
                    isChanged: false,
                },
                listPrice: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').classes()).toContain('sw-button--disabled');

        await wrapper.setData({
            isLoading: false,
            bulkEditProduct: {
                taxId: {
                    isChanged: true,
                },
                price: {
                    isChanged: true,
                },
                purchasePrices: {
                    isChanged: false,
                },
                listPrice: {
                    isChanged: false,
                },
            },
        });
        expect(wrapper.find('.sw-button-process').classes()).not.toContain('sw-button--disabled');
    });

    // TODO: @inventory - Fix this test
    // eslint-disable-next-line jest/no-disabled-tests
    it.skip('should get parent product when component created', async () => {
        wrapper = await createWrapper();
        wrapper.vm.getParentProduct = jest.fn();

        wrapper.vm.createdComponent();
        expect(wrapper.vm.getParentProduct).toHaveBeenCalled();

        wrapper.vm.getParentProduct.mockRestore();
    });

    it('should not be able to get parent product', async () => {
        wrapper = await createWrapper();

        await wrapper.setData({
            $route: {
                params: {
                    parentId: 'null',
                },
            },
        });
        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProduct).toBe(null);
    });

    it('should get parent product successful', async () => {
        wrapper = await createWrapper();
        wrapper.vm.productRepository.get = jest.fn((productId) => {
            if (productId === 'productId') {
                return Promise.resolve({
                    id: 'productId',
                    name: 'productName',
                });
            }
            return Promise.reject();
        });

        await wrapper.setData({
            $route: {
                params: {
                    parentId: 'productId',
                },
            },
        });
        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProductFrozen).toEqual(JSON.stringify({
            id: 'productId',
            name: 'productName',
            stock: null,
        }));
        wrapper.vm.productRepository.get.mockRestore();
    });

    it('should get parent product failed', async () => {
        wrapper = await createWrapper();
        wrapper.vm.productRepository.get = jest.fn((productId) => {
            if (productId === 'productId') {
                return Promise.reject();
            }
            return Promise.resolve({
                id: 'productId',
                name: 'productName',
            });
        });

        await wrapper.setData({
            $route: {
                params: {
                    parentId: 'productId',
                },
            },
        });
        await wrapper.vm.getParentProduct();

        expect(wrapper.vm.parentProduct).toEqual(null);
        expect(wrapper.vm.parentProductFrozen).toEqual(null);
        wrapper.vm.productRepository.get.mockRestore();
    });
});
